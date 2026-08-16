<?php

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a filtered list query into a download, without adding any package.
 *
 *  - "Excel" is a UTF-8 CSV. Excel and Google Sheets both open it directly, and unlike a
 *    generated .xlsx it needs no library and carries no format-warning on open.
 *  - "PDF" is a print-styled HTML page that opens in its own tab and calls the browser's
 *    print dialog — every browser's "Save as PDF" turns that into a real file. Server-side
 *    PDF rendering would mean pulling in dompdf/tcpdf; this keeps the dependency list flat.
 *
 * A page opts in by reading {@see exportFormat()} at the top of its index(), right after
 * the filtered+sorted query is built but before it is paginated, and handing that same
 * query here. Export therefore reflects whatever search/filter/sort the URL carries, and
 * inherits the route's existing view-module authorization for free.
 */
trait ExportsList
{
    /**
     * Upper bound on rows a single export materialises. A directory that has grown past
     * this exports its first N rows (in the current sort order) rather than trying to hold
     * the whole table in memory — the header notes when that cap was hit.
     */
    protected function exportRowCap(): int
    {
        return 5000;
    }

    /**
     * The requested format, but only when it is one we actually serve — an unknown
     * `?export=` value returns null so index() falls through to its normal HTML render.
     */
    protected function exportFormat(Request $request): ?string
    {
        $format = strtolower((string) $request->query('export', ''));

        return in_array($format, ['excel', 'pdf'], true) ? $format : null;
    }

    /**
     * Flatten a grouped column definition (groupLabel => [key => [label, getter]]) into the
     * single key => [label, getter] map {@see exportList()} works from. The grouping only
     * exists for the picker UI; the export itself is one flat list.
     *
     * @param  array<string,array<string,array{0:string,1:callable}>>  $grouped
     * @return array<string,array{0:string,1:callable}>
     */
    protected function flattenGroupedColumns(array $grouped): array
    {
        $flat = [];

        foreach ($grouped as $columns) {
            foreach ($columns as $key => $definition) {
                $flat[$key] = $definition;
            }
        }

        return $flat;
    }

    /**
     * Strip the getters out of a grouped definition, leaving groupLabel => [key => label]
     * for the picker to render as titled sections of checkboxes.
     *
     * @param  array<string,array<string,array{0:string,1:callable}>>  $grouped
     * @return array<string,array<string,string>>
     */
    protected function exportGroupLabels(array $grouped): array
    {
        return array_map(
            fn ($columns) => array_map(fn ($definition) => $definition[0], $columns),
            $grouped,
        );
    }

    /**
     * The columns the export should carry: the intersection of what the page offers and
     * what the user ticked, kept in the page's own definition order (never the click
     * order the checkboxes were selected in). A missing or unrecognisable `columns`
     * param means "everything" — the same as the all-ticked default in the UI.
     *
     * @param  array<int,string>  $available  every column key the page can export
     * @return array<int,string>
     */
    protected function selectedColumns(Request $request, array $available): array
    {
        $raw = $request->query('columns');

        if ($raw === null || $raw === '') {
            return $available;
        }

        $wanted = is_array($raw) ? $raw : explode(',', (string) $raw);

        // Definition order, not $wanted's order — the sheet's columns must not reshuffle
        // based on which boxes were clicked first.
        $selected = array_values(array_filter($available, fn ($key) => in_array($key, $wanted, true)));

        // A garbage param (nothing matched) falls back to all rather than an empty sheet.
        return $selected ?: $available;
    }

    /**
     * Run the (unpaginated) query, map each record to a flat row, and build the response.
     *
     * @param  string  $format  'excel' | 'pdf'
     * @param  Builder  $query  the same filtered+sorted builder index() would paginate
     * @param  string  $filename  base name, no extension or date — both are added here
     * @param  string  $title  heading shown on the PDF page
     * @param  array<string,array{0:string,1:callable}>  $columns  key => [label, fn($record) => cell]
     * @param  Request  $request  read for the `columns` selection
     */
    protected function exportList(string $format, Builder $query, string $filename, string $title, array $columns, Request $request)
    {
        // Narrow to the ticked columns, then split the definitions into a heading list and
        // an aligned list of value-getters so the row builder below stays positional.
        $selected = $this->selectedColumns($request, array_keys($columns));

        $headings = [];
        $getters = [];
        foreach ($selected as $key) {
            [$label, $getter] = $columns[$key];
            $headings[] = $label;
            $getters[] = $getter;
        }

        $mapRow = fn ($record) => array_map(fn ($getter) => $getter($record), $getters);

        $cap = $this->exportRowCap();

        $records = (clone $query)->limit($cap)->get();
        $truncated = $records->count() >= $cap;

        $rows = $records->map(fn ($record) => array_map(
            // Normalise every cell to a string here so both writers stay dumb: nulls become
            // blanks, and a value that is still an array/collection (a JSON column that was
            // never joined into text) is comma-joined rather than printing "Array".
            fn ($cell) => match (true) {
                $cell === null => '',
                is_array($cell) => implode(', ', $cell),
                $cell instanceof \Illuminate\Support\Collection => $cell->implode(', '),
                default => (string) $cell,
            },
            $mapRow($record),
        ))->all();

        $stamp = Carbon::now();
        $file = $filename . '-' . $stamp->format('Y-m-d');

        if ($format === 'pdf') {
            return response()->view('admin.exports.print', [
                'title' => $title,
                // One table rendered through the same sectioned template the dashboard uses;
                // the empty section title suppresses a per-table heading, since the page's
                // own <h1> already names it.
                'sections' => [[
                    'title' => '',
                    'headings' => $headings,
                    'rows' => $rows,
                    'note' => null,
                ]],
                'filename' => $file,
                'truncated' => $truncated,
                'cap' => $cap,
                'generatedAt' => $stamp,
            ]);
        }

        return $this->streamCsvDownload($file, $headings, $rows);
    }

    /**
     * The same two formats for a screen that is several small tables rather than one
     * filtered list — the dashboard, where a single query and a single header row cannot
     * describe what is on the page.
     *
     * Each section is `['title' => …, 'headings' => [...], 'rows' => [[...]], 'note' => ?]`.
     * The CSV stacks them with the title on its own line and a blank line between, which
     * is what Excel and Sheets both read back as separate blocks in one sheet.
     *
     * @param  string  $format  'excel' | 'pdf'
     * @param  array<int,array{title:string,headings:array<int,string>,rows:array<int,array<int,string|int|float|null>>,note?:string}>  $sections
     */
    protected function exportSectioned(string $format, string $filename, string $title, array $sections)
    {
        $sections = array_map(fn ($section) => $section + ['rows' => [], 'note' => null], $sections);

        $stamp = Carbon::now();
        $file = $filename . '-' . $stamp->format('Y-m-d');

        if ($format === 'pdf') {
            return response()->view('admin.exports.print', [
                'title' => $title,
                'sections' => $sections,
                'filename' => $file,
                'generatedAt' => $stamp,
            ]);
        }

        return response()->streamDownload(function () use ($sections) {
            $out = $this->openCsv();

            foreach ($sections as $i => $section) {
                if ($i > 0) {
                    fputcsv($out, [], ',', '"', '');
                }

                fputcsv($out, [$section['title']], ',', '"', '');
                fputcsv($out, $section['headings'], ',', '"', '');

                foreach ($section['rows'] as $row) {
                    fputcsv($out, $row, ',', '"', '');
                }

                if ($section['note']) {
                    fputcsv($out, [$section['note']], ',', '"', '');
                }
            }

            fclose($out);
        }, $file . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int,string>  $headings
     * @param  array<int,array<int,string>>  $rows
     */
    private function streamCsvDownload(string $file, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = $this->openCsv();

            // Explicit escape '' (not the default '\\'): PHP 8.4 deprecates relying on the
            // default, and an empty escape yields RFC-4180 CSV where a quote inside a cell
            // is doubled ("") — which is precisely what Excel expects.
            fputcsv($out, $headings, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $file . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return resource */
    private function openCsv()
    {
        $out = fopen('php://output', 'w');

        // BOM first, so Excel reads the file as UTF-8 — without it, an accented name or
        // the ₹ sign comes out as mojibake in the exact tool this is labelled for.
        fwrite($out, "\xEF\xBB\xBF");

        return $out;
    }
}

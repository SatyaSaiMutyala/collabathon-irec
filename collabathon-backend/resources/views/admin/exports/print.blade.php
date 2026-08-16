<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $filename }}</title>
    <style>
        /* Self-contained on purpose: this page opens in its own tab to be printed, so it
           carries its own styling rather than pulling in the admin bundle. */
        :root { --ink:#1a1d23; --ink-2:#565c66; --ink-3:#868d97; --line:#e3e6ea; --band:#f6f7f9; --accent:#b8860b; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font: 12px/1.45 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink); background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .sheet { max-width: 1100px; margin: 0 auto; padding: 28px 32px 48px; }

        .toolbar {
            position: sticky; top: 0; display: flex; gap: 10px; align-items: center;
            background: #fff; border-bottom: 1px solid var(--line); padding: 12px 32px; margin: -28px -32px 24px;
        }
        .toolbar .spacer { flex: 1; }
        .btn {
            font: inherit; font-weight: 600; font-size: 12.5px; cursor: pointer;
            border: 1px solid var(--line); background: #fff; color: var(--ink);
            padding: 7px 14px; border-radius: 8px;
        }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }

        header.doc { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-bottom: 18px; }
        .brand { display: flex; align-items: center; gap: 9px; }
        .brand .mark {
            width: 26px; height: 26px; border-radius: 7px; background: var(--accent); color: #fff;
            font-weight: 700; font-size: 10px; display: flex; align-items: center; justify-content: center; letter-spacing: -.02em;
        }
        .brand .name { font-weight: 600; font-size: 13px; letter-spacing: -.01em; }
        h1 { font-size: 17px; margin: 0 0 4px; letter-spacing: -.02em; }
        .meta { color: var(--ink-3); font-size: 11px; text-align: right; }
        .meta strong { color: var(--ink-2); font-weight: 600; }

        .notice {
            background: #fff8e6; border: 1px solid #f2e2b0; color: #7a5c00;
            border-radius: 8px; padding: 8px 12px; font-size: 11.5px; margin-bottom: 14px;
        }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .06em;
            color: var(--ink-3); font-weight: 600; padding: 8px 10px; border-bottom: 1.5px solid var(--line);
            white-space: nowrap;
        }
        tbody td { padding: 7px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        tbody tr:nth-child(even) { background: var(--band); }
        tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }

        /* Sectioned exports (the dashboard) — a list export has one untitled section and
           never renders these. */
        h2 {
            font-size: 12.5px; margin: 22px 0 8px; letter-spacing: -.01em;
            padding-bottom: 5px; border-bottom: 1px solid var(--line);
        }
        h2:first-of-type { margin-top: 4px; }
        .section-note { color: var(--ink-3); font-size: 10.5px; margin: 6px 0 0; }

        .empty { padding: 40px; text-align: center; color: var(--ink-3); }
        footer.doc { margin-top: 18px; color: var(--ink-3); font-size: 10.5px; text-align: center; }

        @media print {
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; }
            thead { display: table-header-group; }   /* repeat header on every printed page */
            tbody tr { break-inside: avoid; }
            /* A section heading stranded at the foot of a page, with its table overleaf,
               is the one break this layout can actually get wrong. */
            h2 { break-after: avoid; }
            @page { size: landscape; margin: 12mm; }
        }
    </style>
</head>
@php
    /*
     * Two callers, one sheet: a list export passes a single untitled section, the
     * dashboard passes several titled ones. An empty title suppresses the per-table
     * heading, so a one-table export still reads as one document under its own <h1>.
     */
    $recordCount = array_sum(array_map(fn ($section) => count($section['rows']), $sections));
@endphp
<body>
    <div class="sheet">
        <div class="toolbar">
            <button type="button" class="btn btn-primary" onclick="window.print()">Save as PDF / Print</button>
            <button type="button" class="btn" onclick="window.close()">Close</button>
            <span class="spacer"></span>
            <span style="color:var(--ink-3);font-size:11.5px">Use “Save as PDF” as the print destination.</span>
        </div>

        <header class="doc">
            <div>
                <div class="brand">
                    <span class="mark">iR</span>
                    <span class="name">iREC Admin</span>
                </div>
                <h1 style="margin-top:12px">{{ $title }}</h1>
            </div>
            <div class="meta">
                <div>Generated <strong>{{ $generatedAt->format('d M Y, H:i') }}</strong></div>
                <div>{{ number_format($recordCount) }} {{ Str::plural('record', $recordCount) }}</div>
            </div>
        </header>

        @if($truncated ?? false)
            <div class="notice">
                Showing the first {{ number_format($cap) }} rows in the current sort order. Narrow the list with
                search or filters before exporting to capture a specific subset.
            </div>
        @endif

        @foreach($sections as $section)
            @if($section['title'])
                <h2>{{ $section['title'] }}</h2>
            @endif

            @if(count($section['rows']) === 0)
                <div class="empty">No records match the current filters.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            @foreach($section['headings'] as $heading)
                                <th>{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    {{-- Right-align cells that are purely numeric so counts and
                                         prices line up; text stays left. --}}
                                    <td class="{{ (string) $cell !== '' && preg_match('/^[\d,.\s%₹]+$/u', (string) $cell) ? 'num' : '' }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($section['note'] ?? null)
                <p class="section-note">{{ $section['note'] }}</p>
            @endif
        @endforeach

        <footer class="doc">iREC Platform — exported report. Figures reflect the moment of export.</footer>
    </div>

    <script>
        // Open straight into the print dialog. Wrapped so that dismissing it just leaves the
        // table on screen (with the toolbar) rather than looking broken.
        window.addEventListener('load', () => setTimeout(() => { try { window.print(); } catch (e) {} }, 250));
    </script>
</body>
</html>

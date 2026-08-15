<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Plain-PHP CSV parsing — no Composer package (Laravel Excel, PhpSpreadsheet, …), so a
 * deploy to shared hosting never needs a `composer install` this app doesn't already run.
 * Excel exports "Save As CSV" without losing anything a bulk-import sheet actually needs.
 */
class CsvReader
{
    /**
     * @return array<int, array<string, string>> Row number (1-based, header excluded) => row
     */
    public static function rows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['Could not read that file.']]);
        }

        // A UTF-8 BOM on the first header cell (common from Excel's own "Save As CSV")
        // would otherwise survive into the column name and silently break every
        // `$row['company_name']` lookup for a file that looks completely normal.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');

        if ($header === false || $header === null) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['That file has no header row.']]);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $rows = [];
        $number = 0;

        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $number++;

            // A trailing blank line (near-universal at the end of a spreadsheet export)
            // would otherwise show up as row N, every column empty, and fail whatever
            // required-field checks the caller runs — as if the sheet itself were broken.
            if ($line === [null] || $line === false) {
                continue;
            }
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = trim((string) ($line[$i] ?? ''));
            }

            $rows[$number] = $row;
        }

        fclose($handle);

        return $rows;
    }
}

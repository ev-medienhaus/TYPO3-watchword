<?php

declare(strict_types=1);

namespace Emh\Watchword\Service;

use Emh\Watchword\Domain\Repository\WatchwordRepository;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parses, validates and imports the yearly "Losungen" Excel/CSV export.
 *
 * Import always happens in two steps (parseAndValidate for the preview, then insert
 * for the confirmed batch) and parseAndValidate is deliberately re-run on confirm as
 * well, so nothing client-supplied is ever trusted for the actual write.
 */
class WatchwordImportService
{
    /**
     * Column name => required flag. Order in the source file is irrelevant, the header
     * row is mapped to column indexes by name.
     */
    private const EXPECTED_COLUMNS = [
        'Datum' => true,
        'Wtag' => true,
        'Sonntag' => false,
        'Losungsvers' => true,
        'Losungstext' => true,
        'Lehrtextvers' => true,
        'Lehrtext' => true,
    ];

    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];

    public function __construct(
        private readonly WatchwordRepository $watchwordRepository,
    ) {}

    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    public function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true);
    }

    public function getTransientDirectory(): string
    {
        $directory = Environment::getVarPath() . '/transient/watchword/';
        if (!is_dir($directory)) {
            GeneralUtility::mkdir_deep($directory);
        }

        return $directory;
    }

    /**
     * Parses and validates the given file. Never writes to the database.
     *
     * @return array{
     *     headerValid: bool,
     *     headerError: string,
     *     newRows: array<int, array{date:int, year:int, weekday:string, sundayName:string, watchwordVerse:string, watchwordText:string, teachingVerse:string, teachingText:string}>,
     *     duplicateCount: int,
     *     invalidRows: array<int, array{row:int, reason:string}>,
     * }
     */
    public function parseAndValidate(string $filePath): array
    {
        $result = [
            'headerValid' => false,
            'headerError' => '',
            'newRows' => [],
            'duplicateCount' => 0,
            'invalidRows' => [],
        ];

        try {
            $reader = $this->createReaderForFile($filePath);
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            $result['headerError'] = $e->getMessage();

            return $result;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $columnMap = $this->mapHeaderColumns($sheet);

        $missingColumns = array_diff(array_keys(self::EXPECTED_COLUMNS), array_keys($columnMap));
        if ($missingColumns !== []) {
            $result['headerError'] = implode(', ', $missingColumns);

            return $result;
        }

        $result['headerValid'] = true;

        $parsedRows = [];
        $seenInFile = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = [];
            foreach ($columnMap as $columnName => $columnIndex) {
                $values[$columnName] = $sheet->getCell([$columnIndex + 1, $rowNumber]);
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $reasons = [];
            $date = $this->parseDate($values['Datum']);
            if ($date === null) {
                $reasons[] = 'Datum ungültig oder leer';
            }

            foreach (self::EXPECTED_COLUMNS as $columnName => $required) {
                if (!$required || $columnName === 'Datum') {
                    continue;
                }
                if (trim((string)$values[$columnName]->getFormattedValue()) === '') {
                    $reasons[] = $columnName . ' ist leer';
                }
            }

            if ($reasons !== []) {
                $result['invalidRows'][] = [
                    'row' => $rowNumber,
                    'reason' => implode('; ', $reasons),
                ];

                continue;
            }

            $timestamp = $date->getTimestamp();

            if (isset($seenInFile[$timestamp])) {
                $result['duplicateCount']++;

                continue;
            }
            $seenInFile[$timestamp] = true;

            $parsedRows[$timestamp] = [
                'date' => $timestamp,
                'year' => (int)$date->format('Y'),
                'weekday' => trim((string)$values['Wtag']->getFormattedValue()),
                'sundayName' => trim((string)$values['Sonntag']->getFormattedValue()),
                'watchwordVerse' => trim((string)$values['Losungsvers']->getFormattedValue()),
                'watchwordText' => trim((string)$values['Losungstext']->getFormattedValue()),
                'teachingVerse' => trim((string)$values['Lehrtextvers']->getFormattedValue()),
                'teachingText' => trim((string)$values['Lehrtext']->getFormattedValue()),
            ];
        }

        if ($parsedRows !== []) {
            $existingDates = $this->watchwordRepository->findExistingDates(array_keys($parsedRows));
            foreach ($existingDates as $existingDate) {
                if (isset($parsedRows[$existingDate])) {
                    unset($parsedRows[$existingDate]);
                    $result['duplicateCount']++;
                }
            }
        }

        $result['newRows'] = array_values($parsedRows);

        return $result;
    }

    /**
     * @param array<int, array{date:int, year:int, weekday:string, sundayName:string, watchwordVerse:string, watchwordText:string, teachingVerse:string, teachingText:string}> $newRows
     */
    public function insert(array $newRows, int $storagePid, int $cruserId): int
    {
        if ($newRows === []) {
            return 0;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(WatchwordRepository::TABLE);

        $connection->beginTransaction();
        try {
            $now = time();
            foreach ($newRows as $row) {
                $connection->insert(WatchwordRepository::TABLE, [
                    'pid' => $storagePid,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'cruser_id' => $cruserId,
                    'date' => $row['date'],
                    'year' => $row['year'],
                    'weekday' => $row['weekday'],
                    'sunday_name' => $row['sundayName'],
                    'watchword_verse' => $row['watchwordVerse'],
                    'watchword_text' => $row['watchwordText'],
                    'teaching_verse' => $row['teachingVerse'],
                    'teaching_text' => $row['teachingText'],
                ]);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return count($newRows);
    }

    public function deleteTempFile(string $filePath): void
    {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * @return array<string, int> column name => 0-based column index
     */
    private function mapHeaderColumns(Worksheet $sheet): array
    {
        $map = [];
        $highestColumn = $sheet->getHighestDataColumn(1);
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $value = trim((string)$sheet->getCell([$columnIndex, 1])->getValue());
            if ($value !== '') {
                $map[$value] = $columnIndex - 1;
            }
        }

        return $map;
    }

    /**
     * @param array<string, Cell> $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $cell) {
            if (trim((string)$cell->getFormattedValue()) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseDate(Cell $cell): ?\DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');

        if (ExcelDate::isDateTime($cell)) {
            $dateTime = ExcelDate::excelToDateTimeObject($cell->getCalculatedValue(), $utc);

            return \DateTimeImmutable::createFromMutable($dateTime)->setTime(0, 0, 0);
        }

        $value = trim((string)$cell->getValue());
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float)$value > 25569) {
            $dateTime = ExcelDate::excelToDateTimeObject((float)$value, $utc);

            return \DateTimeImmutable::createFromMutable($dateTime)->setTime(0, 0, 0);
        }

        foreach (['d.m.Y', 'Y-m-d', 'd.m.y'] as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat('!' . $format, $value, $utc);
            if ($dateTime !== false) {
                return $dateTime;
            }
        }

        return null;
    }

    private function createReaderForFile(string $filePath): IReader
    {
        $reader = IOFactory::createReaderForFile($filePath);
        if ($reader instanceof Csv) {
            $reader->setDelimiter($this->detectCsvDelimiter($filePath));
            $reader->setInputEncoding(Csv::guessEncoding($filePath));
        }

        return $reader;
    }

    private function detectCsvDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        $firstLine = $handle !== false ? (string)fgets($handle) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        $best = ',';
        $bestCount = -1;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }
}

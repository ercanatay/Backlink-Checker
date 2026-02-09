<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Exceptions\ValidationException;

final class ExportService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    public function requestExport(int $scanId, int $projectId, int $requestedBy, string $format): int
    {
        $format = strtolower($format);
        if (!in_array($format, ['csv', 'txt', 'xlsx', 'json'], true)) {
            throw new ValidationException('Invalid export format');
        }

        $this->db->execute(
            'INSERT INTO exports(scan_id, project_id, format, status, requested_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$scanId, $projectId, $format, 'queued', $requestedBy, gmdate('c')]
        );
        $exportId = $this->db->lastInsertId();

        $this->buildExport($exportId);

        return $exportId;
    }

    public function buildExport(int $exportId): void
    {
        $export = $this->db->fetchOne('SELECT * FROM exports WHERE id = ?', [$exportId]);
        if ($export === null) {
            return;
        }

        $rows = $this->db->fetchAll(
            'SELECT r.*, t.url AS target_url FROM scan_results r JOIN scan_targets t ON t.id = r.target_id WHERE r.scan_id = ? ORDER BY r.id ASC',
            [$export['scan_id']]
        );

        $dir = $this->config->string('EXPORT_ABSOLUTE_PATH');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'scan_' . $export['scan_id'] . '_export_' . $exportId . '.' . $export['format'];
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        switch ($export['format']) {
            case 'csv':
                $this->writeCsv($path, $rows, ',');
                break;
            case 'txt':
                $this->writeCsv($path, $rows, "\t");
                break;
            case 'json':
                file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                break;
            case 'xlsx':
                $this->writeXlsx($path, $rows);
                break;
        }

        $this->db->execute(
            'UPDATE exports SET status = ?, file_path = ?, completed_at = ? WHERE id = ?',
            ['ready', $path, gmdate('c'), $exportId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForProject(int $exportId, int $projectId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM exports WHERE id = ? AND project_id = ?', [$exportId, $projectId]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $path, array $rows, string $delimiter): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            return;
        }

        $headers = ['Source URL', 'Source Domain', 'Final URL', 'HTTP Status', 'Meta Noindex', 'X-Robots Noindex', 'Backlink', 'Link Type', 'Anchor Text', 'DA', 'PA', 'Error'];
        fputcsv($fh, $headers, $delimiter);

        foreach ($rows as $row) {
            fputcsv($fh, [
                $row['source_url'] ?? '',
                $row['source_domain'] ?? '',
                $row['final_url'] ?? '',
                $row['http_status'] ?? '',
                (int) ($row['robots_noindex'] ?? 0) === 1 ? 'Yes' : 'No',
                (int) ($row['x_robots_noindex'] ?? 0) === 1 ? 'Yes' : 'No',
                (int) ($row['backlink_found'] ?? 0) === 1 ? 'Yes' : 'No',
                $row['best_link_type'] ?? 'none',
                $row['anchor_text'] ?? '',
                $row['domain_authority'] ?? '',
                $row['page_authority'] ?? '',
                $row['error_message'] ?? '',
            ], $delimiter);
        }

        fclose($fh);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeXlsx(string $path, array $rows): void
    {
        $headers = ['Source URL', 'Source Domain', 'Final URL', 'HTTP Status', 'Meta Noindex', 'X-Robots Noindex', 'Backlink', 'Link Type', 'Anchor Text', 'DA', 'PA', 'Error'];

        $sheetRows = [];
        $sheetRows[] = $headers;
        foreach ($rows as $row) {
            $sheetRows[] = [
                (string) ($row['source_url'] ?? ''),
                (string) ($row['source_domain'] ?? ''),
                (string) ($row['final_url'] ?? ''),
                (string) ($row['http_status'] ?? ''),
                ((int) ($row['robots_noindex'] ?? 0) === 1) ? 'Yes' : 'No',
                ((int) ($row['x_robots_noindex'] ?? 0) === 1) ? 'Yes' : 'No',
                ((int) ($row['backlink_found'] ?? 0) === 1) ? 'Yes' : 'No',
                (string) ($row['best_link_type'] ?? 'none'),
                (string) ($row['anchor_text'] ?? ''),
                (string) ($row['domain_authority'] ?? ''),
                (string) ($row['page_authority'] ?? ''),
                (string) ($row['error_message'] ?? ''),
            ];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($sheetRows));
        $zip->close();
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Results" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($row as $cIdx => $cell) {
                $cellRef = $this->columnName($cIdx) . $rowNum;
                $escaped = htmlspecialchars($cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function columnName(int $index): string
    {
        $name = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $name = chr($n % 26 + 65) . $name;
            $n = intdiv($n, 26);
        }

        return $name;
    }

    public function isValidExportPath(string $path): bool
    {
        $allowedDir = realpath($this->config->string('EXPORT_ABSOLUTE_PATH'));
        $targetPath = realpath($path);

        if ($allowedDir === false || $targetPath === false) {
            return false;
        }

        return str_starts_with($targetPath, $allowedDir . DIRECTORY_SEPARATOR);
    }
}

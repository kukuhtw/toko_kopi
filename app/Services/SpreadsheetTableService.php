<?php

declare(strict_types=1);

namespace App\Services;

class SpreadsheetTableService
{
    public function readTables(string $path, string $originalName = ''): array
    {
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv' => [$this->readCsvTable($path)],
            'xlsx' => $this->readXlsxTables($path),
            'xls' => $this->readXlsTables($path),
            default => throw new \RuntimeException('Format file tidak didukung. Gunakan CSV, XLSX, atau XLS (Excel XML).'),
        };
    }

    public function renderXmlWorkbook(array $sheets): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0"?>';
        $xml[] = '<?mso-application progid="Excel.Sheet"?>';
        $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        $xml[] = ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        $xml[] = ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:html="http://www.w3.org/TR/REC-html40">';
        $xml[] = '<Styles>';
        $xml[] = '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#D9EDF7" ss:Pattern="Solid"/></Style>';
        $xml[] = '<Style ss:ID="Note"><Font ss:Bold="1"/><Interior ss:Color="#FFF4CC" ss:Pattern="Solid"/></Style>';
        $xml[] = '</Styles>';

        foreach ($sheets as $sheet) {
            $name = $this->escapeXml($this->normalizeSheetName((string)($sheet['name'] ?? 'Sheet1')));
            $rows = is_array($sheet['rows'] ?? null) ? $sheet['rows'] : [];
            $xml[] = '<Worksheet ss:Name="' . $name . '"><Table>';

            foreach ($rows as $rowIndex => $row) {
                $xml[] = '<Row>';
                foreach ((array)$row as $value) {
                    $style = '';
                    if ($rowIndex === 0) {
                        $style = ' ss:StyleID="Header"';
                    } elseif ($rowIndex === 1 && isset($row[0]) && str_starts_with((string)$row[0], '__NOTE__')) {
                        $style = ' ss:StyleID="Note"';
                    }
                    $cellValue = $rowIndex === 1 && isset($row[0]) && str_starts_with((string)$row[0], '__NOTE__')
                        ? preg_replace('/^__NOTE__\s*/', '', (string)$value) ?? (string)$value
                        : (string)$value;
                    $xml[] = '<Cell' . $style . '><Data ss:Type="String">' . $this->escapeXml($cellValue) . '</Data></Cell>';
                }
                $xml[] = '</Row>';
            }

            $xml[] = '</Table></Worksheet>';
        }

        $xml[] = '</Workbook>';
        return implode('', $xml);
    }

    private function readCsvTable(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membaca file CSV.');
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = array_map([$this, 'normalizeCell'], $row);
        }
        fclose($handle);

        return [
            'name' => 'CSV Import',
            'rows' => $rows,
        ];
    }

    private function readXlsTables(string $path): array
    {
        $raw = (string)file_get_contents($path);
        if (stripos($raw, '<Workbook') !== false) {
            return $this->readXmlWorkbookTables($raw);
        }

        if (str_starts_with($raw, 'PK')) {
            return $this->readXlsxTables($path);
        }

        throw new \RuntimeException('Format .xls biner lama belum didukung. Gunakan file .xlsx atau export XLS dari sistem ini.');
    }

    private function readXmlWorkbookTables(string $xmlString): array
    {
        $xml = @simplexml_load_string($xmlString);
        if (!$xml) {
            throw new \RuntimeException('File XLS XML tidak valid.');
        }

        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $worksheets = $xml->xpath('//ss:Worksheet') ?: [];
        $tables = [];

        foreach ($worksheets as $worksheet) {
            $nameAttr = $worksheet->attributes('urn:schemas-microsoft-com:office:spreadsheet');
            $sheetName = (string)($nameAttr['Name'] ?? 'Sheet');
            $rows = [];

            foreach ($worksheet->xpath('./ss:Table/ss:Row') ?: [] as $rowNode) {
                $row = [];
                foreach ($rowNode->xpath('./ss:Cell') ?: [] as $cellNode) {
                    $dataNodes = $cellNode->xpath('./ss:Data');
                    $row[] = $this->normalizeCell((string)($dataNodes[0] ?? ''));
                }
                $rows[] = $row;
            }

            $tables[] = ['name' => $sheetName, 'rows' => $rows];
        }

        return $tables;
    }

    private function readXlsxTables(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi ZipArchive tidak tersedia untuk membaca XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka file XLSX.');
        }

        $sharedStrings = $this->readSharedStringsFromZip($zip);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relsXml)) {
            $zip->close();
            throw new \RuntimeException('Struktur XLSX tidak lengkap.');
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if (!$workbook || !$rels) {
            $zip->close();
            throw new \RuntimeException('Gagal membaca XML workbook XLSX.');
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $relMap = [];
        foreach ($rels->xpath('//r:Relationship') ?: [] as $rel) {
            $attrs = $rel->attributes();
            $relMap[(string)$attrs['Id']] = 'xl/' . ltrim((string)$attrs['Target'], '/');
        }

        $tables = [];
        foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
            $attrs = $sheet->attributes();
            $relAttrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relId = (string)($relAttrs['id'] ?? '');
            $sheetPath = $relMap[$relId] ?? '';
            if ($sheetPath === '') {
                continue;
            }

            $sheetXml = $zip->getFromName($sheetPath);
            if (!is_string($sheetXml)) {
                continue;
            }
            $tables[] = [
                'name' => (string)($attrs['name'] ?? 'Sheet'),
                'rows' => $this->readXlsxSheetRows($sheetXml, $sharedStrings),
            ];
        }

        $zip->close();
        return $tables;
    }

    private function readSharedStringsFromZip(\ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($sharedXml) || $sharedXml === '') {
            return [];
        }

        $xml = simplexml_load_string($sharedXml);
        if (!$xml) {
            return [];
        }
        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($xml->xpath('//a:si') ?: [] as $si) {
            $parts = [];
            foreach ($si->xpath('.//a:t') ?: [] as $textNode) {
                $parts[] = (string)$textNode;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function readXlsxSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $xml = simplexml_load_string($sheetXml);
        if (!$xml) {
            return [];
        }
        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xml->xpath('//a:sheetData/a:row') ?: [] as $rowNode) {
            $row = [];
            $currentIndex = 0;

            foreach ($rowNode->xpath('./a:c') ?: [] as $cellNode) {
                $attrs = $cellNode->attributes();
                $ref = (string)($attrs['r'] ?? '');
                $type = (string)($attrs['t'] ?? '');
                $targetIndex = $ref !== '' ? $this->columnLettersToIndex(preg_replace('/\d+/', '', $ref) ?: 'A') : $currentIndex;

                while ($currentIndex < $targetIndex) {
                    $row[] = '';
                    $currentIndex++;
                }

                $valueNode = $cellNode->xpath('./a:v');
                $value = (string)($valueNode[0] ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                }
                $inlineNodes = $cellNode->xpath('./a:is/a:t');
                if ($inlineNodes) {
                    $value = (string)$inlineNodes[0];
                }

                $row[] = $this->normalizeCell($value);
                $currentIndex++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private function normalizeCell(mixed $value): string
    {
        $value = (string)$value;
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        return trim($value);
    }

    private function normalizeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '-', $name) ?? $name;
        $name = trim($name);
        if ($name === '') {
            $name = 'Sheet';
        }
        return mb_substr($name, 0, 31, 'UTF-8');
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

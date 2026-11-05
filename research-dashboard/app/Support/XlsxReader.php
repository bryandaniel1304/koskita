<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Pembaca .xlsx minimal (ZipArchive + SimpleXML, tanpa pustaka tambahan)
 * untuk mengimpor rekap survei. Hanya membaca nilai sel -- rumus diambil
 * nilai tersimpannya, format tampilan diabaikan.
 */
class XlsxReader
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Baris sebuah sheet sebagai array asosiatif berkunci judul kolom (baris
     * pertama). Baris yang seluruh selnya kosong dilewati.
     *
     * @return array<int, array<string, string>>
     */
    public static function rows(string $path, ?string $sheetName = null): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Berkas tidak bisa dibuka sebagai .xlsx: {$path}");
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::sheetPath($zip, $sheetName));
            if ($sheetXml === false) {
                throw new RuntimeException('Sheet tidak ditemukan di dalam berkas .xlsx.');
            }
        } finally {
            $zip->close();
        }

        $sheet = new SimpleXMLElement($sheetXml);
        $sheet->registerXPathNamespace('m', self::MAIN_NS);

        $grid = [];
        foreach ($sheet->xpath('//m:sheetData/m:row') as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $cells[self::columnIndex((string) $cell['r'])] = self::cellValue($cell, $shared);
            }
            if (array_filter($cells, fn ($v) => $v !== '') !== []) {
                $grid[] = $cells;
            }
        }

        if ($grid === []) {
            return [];
        }

        $headers = array_map('trim', array_shift($grid));
        $rows = [];
        foreach ($grid as $cells) {
            $record = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $record[$header] = $cells[$index] ?? '';
                }
            }
            $rows[] = $record;
        }

        return $rows;
    }

    /** @return array<int, string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ((new SimpleXMLElement($xml))->si as $item) {
            // Teks berformat (rich text) terpecah ke beberapa <r><t>.
            $strings[] = isset($item->t) ? (string) $item->t : implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
        }

        return $strings;
    }

    private static function sheetPath(ZipArchive $zip, ?string $sheetName): string
    {
        $workbook = new SimpleXMLElement($zip->getFromName('xl/workbook.xml'));
        $rels = new SimpleXMLElement($zip->getFromName('xl/_rels/workbook.xml.rels'));

        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $targets[(string) $rel['Id']] = (string) $rel['Target'];
        }

        foreach ($workbook->sheets->sheet as $sheet) {
            if ($sheetName === null || (string) $sheet['name'] === $sheetName) {
                $relId = (string) $sheet->attributes(self::REL_NS)['id'];
                $target = ltrim($targets[$relId] ?? '', '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        throw new RuntimeException("Sheet \"{$sheetName}\" tidak ada di dalam berkas .xlsx.");
    }

    private static function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        return match ((string) $cell['t']) {
            's' => $shared[(int) $cell->v] ?? '',
            'inlineStr' => (string) $cell->is->t,
            default => (string) $cell->v,
        };
    }

    /** "A1" -> 0, "R12" -> 17, "AA3" -> 26. */
    private static function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $match);
        $index = 0;
        foreach (str_split($match[0]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}

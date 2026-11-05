<?php

namespace Tests\Feature;

use App\Models\PopulixSusResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class PopulixSusTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['No', 'Id Populix', 'Gender', 'Usia', 'Provinsi', 'Kota', 'Waktu Selesai',
        'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10', 'Skor SUS'];

    private function populixRow(string $id, array $answers, string $score, string $gender = 'Laki-Laki'): array
    {
        return array_merge(['1', $id, $gender, '26', 'Banten', 'Tangerang', '29/10/2026 18:05:13'], array_map('strval', $answers), [$score]);
    }

    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'populix') . '.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, self::HEADERS);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    /** .xlsx minimal (sel inlineStr) dengan sheet "Skor SUS". */
    private function writeXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'populix') . '.xlsx';
        $sheetRows = '';
        foreach (array_merge([self::HEADERS], $rows) as $r => $cells) {
            $sheetRows .= '<row r="' . ($r + 1) . '">';
            foreach ($cells as $c => $value) {
                $ref = chr(65 + $c) . ($r + 1);
                $sheetRows .= is_numeric($value)
                    ? "<c r=\"{$ref}\"><v>{$value}</v></c>"
                    : "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>" . htmlspecialchars($value) . '</t></is></c>';
            }
            $sheetRows .= '</row>';
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Ringkasan" sheetId="2" r:id="rId2"/><sheet name="Skor SUS" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>');
        $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Jumlah responden</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    public function test_csv_import_stores_answers_and_is_idempotent(): void
    {
        $path = $this->writeCsv([
            $this->populixRow('7509634', [5, 1, 5, 1, 5, 1, 4, 1, 5, 1], '97.5'),
            $this->populixRow('7509648', [4, 1, 4, 2, 4, 2, 4, 2, 2, 3], '70', 'Perempuan'),
        ]);

        $this->artisan('sus:import-populix', ['file' => $path])->assertSuccessful();
        $this->artisan('sus:import-populix', ['file' => $path])
            ->expectsOutputToContain('0 baru, 2 diperbarui')
            ->assertSuccessful();

        $this->assertSame(2, PopulixSusResponse::count());
        $first = PopulixSusResponse::where('populix_id', '7509634')->first();
        $this->assertSame(97.5, $first->sus_score);
        $this->assertSame(26, $first->age);
        $this->assertSame('Tangerang', $first->city);
        $this->assertSame('2026-10-29 18:05:13', $first->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_xlsx_import_reads_the_named_sheet(): void
    {
        $path = $this->writeXlsx([
            $this->populixRow('7510032', [5, 1, 5, 1, 5, 1, 5, 1, 5, 1], '100'),
        ]);

        $this->artisan('sus:import-populix', ['file' => $path])->assertSuccessful();

        $this->assertSame(100.0, PopulixSusResponse::where('populix_id', '7510032')->value('sus_score'));
    }

    public function test_invalid_rows_abort_the_whole_import(): void
    {
        $path = $this->writeCsv([
            $this->populixRow('1', [5, 1, 5, 1, 5, 1, 5, 1, 5, 1], '100'),
            $this->populixRow('2', [5, 1, 5, 1, 5, 1, 5, 1, 5, 6], '90'),
            $this->populixRow('3', [5, 1, 5, 1, 5, 1, 5, 1, 5, 1], '90'),
        ]);

        $this->artisan('sus:import-populix', ['file' => $path])
            ->expectsOutputToContain('Q10 harus angka 1-5')
            ->expectsOutputToContain('berbeda dengan hitungan rumus Brooke')
            ->assertFailed();

        $this->assertSame(0, PopulixSusResponse::count());
    }

    public function test_sus_page_combines_both_sources_and_filters_them(): void
    {
        DB::connection('source')->table('sus_responses')->insert([
            'respondent_name' => 'Ani', 'respondent_email' => 'ani@example.com',
            'q1' => 5, 'q2' => 1, 'q3' => 5, 'q4' => 1, 'q5' => 5, 'q6' => 1, 'q7' => 5, 'q8' => 1, 'q9' => 5, 'q10' => 1,
            'sus_score' => 100.0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // 80 tanpa penanda; 50 = jawaban sama semua.
        foreach ([['7509001', [5, 2, 5, 2, 4, 2, 4, 2, 4, 2], 80.0], ['7509002', array_fill(0, 10, 3), 50.0]] as [$id, $answers, $score]) {
            PopulixSusResponse::create(['populix_id' => $id, 'sus_score' => $score, 'gender' => 'Perempuan', 'age' => 24, 'province' => 'Banten']
                + array_combine(array_map(fn ($n) => "q$n", range(1, 10)), $answers));
        }

        $this->get('/sus')->assertOk()
            ->assertSee('Semua sumber (3)')
            ->assertSee('76.7')               // (100 + 80 + 50) / 3
            ->assertSee('Populix #7509002')
            ->assertSee('Ani')
            ->assertSee('Sama semua');

        $this->get('/sus?sumber=populix')->assertOk()
            ->assertSee('65.0')               // (80 + 50) / 2
            ->assertDontSee('ani@example.com')
            ->assertSee('Karakteristik Responden Populix (2)');

        $this->get('/sus?sumber=populix&kualitas=bersih')->assertOk()
            ->assertSee('80.0')
            ->assertDontSee('Populix #7509002');

        $this->get('/')->assertOk()->assertSee('KosKita 1 · Populix 2');
    }
}

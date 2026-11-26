<?php

namespace Tests\Feature;

use App\Models\SusResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Master admin bisa mengunggah file jawaban SUS (ekspor Google Form / Excel /
 * CSV); kolom dikenali dari judulnya, bukan dari urutannya.
 */
class AdminSusImportTest extends TestCase
{
    use RefreshDatabase;

    private function masterAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_master_admin' => true]);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'sus') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'Respons Google Form.xlsx', null, null, true);
    }

    private function csv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sus') . '.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'sus.csv', 'text/csv', null, true);
    }

    public function test_google_form_export_with_statement_headers_in_any_order_is_imported(): void
    {
        $q = SusResponse::QUESTIONS;
        // Urutan kolom diacak, judul = teks pernyataan berawalan nomor seperti di Google Form.
        $header = ['Timestamp', 'Email Address', 'Nama Lengkap', "3. {$q[3]}", "1. {$q[1]}", "2. {$q[2]}",
            "4. {$q[4]}", "5. {$q[5]}", "6. {$q[6]}", "7. {$q[7]}", "8. {$q[8]}", "9. {$q[9]}", "10. {$q[10]}", 'Kesan & Saran'];
        $file = $this->xlsx([
            $header,
            // Q3, Q1, Q2, Q4..Q10
            ['26/11/2026 13:27:05', '', 'Andi', 5, 5, 1, 1, 5, 1, 5, 1, 5, 1, 'Mudah dipakai'],
            ['26/11/2026 14:00:00', 'budi@example.com', 'Budi', 'Setuju', 'Sangat Setuju', 'Tidak Setuju', '2', 'Netral', '3', '4', '2', '4', 'Sangat Tidak Setuju', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''], // baris kosong diabaikan
        ]);

        $this->actingAs($this->masterAdmin())
            ->post('/admin/kuesioner-sus/import', ['file' => $file])
            ->assertRedirect('/admin/kuesioner-sus')
            ->assertSessionHas('success', '2 jawaban berhasil diimpor dari Respons Google Form.xlsx.');

        $andi = SusResponse::where('respondent_name', 'Andi')->firstOrFail();
        $this->assertSame([5, 1, 5, 1, 5, 1, 5, 1, 5, 1], array_map(fn ($n) => $andi->{"q$n"}, range(1, 10)));
        $this->assertSame(100.0, $andi->sus_score);
        $this->assertNull($andi->respondent_email);
        $this->assertSame('import', $andi->source);
        $this->assertSame('2026-11-26 13:27:05', $andi->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('Mudah dipakai', $andi->feedback);

        $budi = SusResponse::where('respondent_name', 'Budi')->firstOrFail();
        $this->assertSame([5, 2, 4, 2, 3, 3, 4, 2, 4, 1], array_map(fn ($n) => $budi->{"q$n"}, range(1, 10)));
        $this->assertSame(SusResponse::calculateScore([5, 2, 4, 2, 3, 3, 4, 2, 4, 1]), $budi->sus_score);
        $this->assertSame('budi@example.com', $budi->respondent_email);

        $csv = $this->actingAs($this->masterAdmin())->get('/admin/kuesioner-sus/export')->streamedContent();
        $this->assertStringContainsString('Impor file', $csv);
    }

    public function test_csv_with_q_headers_is_imported_and_reupload_skips_duplicates(): void
    {
        $rows = [
            ['Nama', 'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10', 'Skor SUS'],
            ['Citra', 4, 2, 4, 2, 4, 2, 4, 2, 4, 2, 75],
            ['Dewi', 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 50],
        ];
        $admin = $this->masterAdmin();

        $this->actingAs($admin)->post('/admin/kuesioner-sus/import', ['file' => $this->csv($rows)])
            ->assertSessionHas('success', '2 jawaban berhasil diimpor dari sus.csv.');
        $this->assertSame(75.0, SusResponse::where('respondent_name', 'Citra')->value('sus_score'));

        $this->actingAs($admin)->post('/admin/kuesioner-sus/import', ['file' => $this->csv($rows)])
            ->assertSessionHas('success', '0 jawaban berhasil diimpor dari sus.csv. 2 baris dilewati karena sudah ada.');
        $this->assertSame(2, SusResponse::count());
    }

    public function test_invalid_row_rejects_the_whole_file(): void
    {
        $file = $this->csv([
            ['Nama', 'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10'],
            ['Eka', 4, 2, 4, 2, 4, 2, 4, 2, 4, 2],
            ['Fani', 4, 2, 7, 2, 4, 2, 4, 2, 4, 2],
            ['', 4, 2, 4, 2, 4, 2, 4, 2, 4, 2],
        ]);

        $this->actingAs($this->masterAdmin())
            ->post('/admin/kuesioner-sus/import', ['file' => $file])
            ->assertRedirect('/admin/kuesioner-sus')
            ->assertSessionHas('import_errors', ['Baris 3: Q3 tidak valid ("7").', 'Baris 4: nama kosong.']);

        $this->assertSame(0, SusResponse::count());
    }

    public function test_missing_question_columns_are_reported(): void
    {
        $file = $this->csv([
            ['Nama', 'Q1', 'Q2', 'Q3'],
            ['Gita', 4, 2, 4],
        ]);

        $this->actingAs($this->masterAdmin())
            ->post('/admin/kuesioner-sus/import', ['file' => $file])
            ->assertSessionHas('import_errors', ['Kolom berikut tidak ditemukan: Q4, Q5, Q6, Q7, Q8, Q9, Q10.']);

        $this->assertSame(0, SusResponse::count());
        $this->actingAs($this->masterAdmin())->get('/admin/kuesioner-sus')->assertOk()->assertSee('Unggah Excel');
    }

    public function test_regular_admin_cannot_import(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_master_admin' => false]);
        $file = $this->csv([['Nama', 'Q1'], ['X', 1]]);

        $this->actingAs($admin)->post('/admin/kuesioner-sus/import', ['file' => $file])->assertForbidden();
        $this->actingAs($admin)->get('/admin/kuesioner-sus')->assertOk()->assertDontSee('Unggah Excel');
    }
}

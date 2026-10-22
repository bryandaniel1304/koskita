<?php

namespace Tests\Unit;

use App\Models\SusResponse;
use PHPUnit\Framework\TestCase;

class SusResponseTest extends TestCase
{
    public function test_questions_are_the_validated_indonesian_adaptation_verbatim(): void
    {
        // Sharfina & Santoso (2016) -- harus identik dengan Tabel 4.27 Buku TA.
        $this->assertCount(10, SusResponse::QUESTIONS);
        $this->assertSame('Saya berpikir akan menggunakan sistem ini lagi.', SusResponse::QUESTIONS[1]);
        $this->assertSame('Saya merasa tidak ada hambatan dalam menggunakan sistem ini.', SusResponse::QUESTIONS[9]);
        $this->assertSame('Saya perlu membiasakan diri terlebih dahulu sebelum menggunakan sistem ini.', SusResponse::QUESTIONS[10]);
    }

    public function test_score_follows_brooke_formula(): void
    {
        $this->assertSame(100.0, SusResponse::calculateScore([5, 1, 5, 1, 5, 1, 5, 1, 5, 1]));
        $this->assertSame(0.0, SusResponse::calculateScore([1, 5, 1, 5, 1, 5, 1, 5, 1, 5]));
        $this->assertSame(50.0, SusResponse::calculateScore([3, 3, 3, 3, 3, 3, 3, 3, 3, 3]));
        // (4-1)+(5-2)+(4-1)+(5-1)+(4-1)+(5-2)+(3-1)+(5-2)+(4-1)+(5-3) = 29 -> 72,5
        $this->assertSame(72.5, SusResponse::calculateScore([4, 2, 4, 1, 4, 2, 3, 2, 4, 3]));
    }

    public function test_interpretation_uses_sauro_lewis_curved_grading_scale_boundaries(): void
    {
        $this->assertSame('A+', SusResponse::interpret(84.1));
        $this->assertSame('A', SusResponse::interpret(84.0));
        $this->assertSame('A', SusResponse::interpret(80.8));
        $this->assertSame('A-', SusResponse::interpret(80.7));
        $this->assertSame('C', SusResponse::interpret(68.0));
        $this->assertSame('C', SusResponse::interpret(65.0));
        $this->assertSame('C-', SusResponse::interpret(64.9));
        $this->assertSame('D', SusResponse::interpret(51.7));
        $this->assertSame('F', SusResponse::interpret(51.6));
        $this->assertSame('41–59', SusResponse::percentileRange(68.0));
    }

    public function test_scores_between_table_rows_round_to_one_decimal_like_the_table(): void
    {
        $this->assertSame('A+', SusResponse::interpret(84.06));
        $this->assertSame('A', SusResponse::interpret(84.04));
    }
}

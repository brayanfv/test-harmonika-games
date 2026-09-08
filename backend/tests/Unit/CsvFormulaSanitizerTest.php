<?php

namespace Tests\Unit;

use App\Support\CsvFormulaSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvFormulaSanitizerTest extends TestCase
{
    #[DataProvider('formulaValues')]
    public function test_formula_like_values_are_neutralized(string $value): void
    {
        $this->assertSame("'{$value}", CsvFormulaSanitizer::sanitize($value));
    }

    public static function formulaValues(): array
    {
        return [
            'equals' => ['=SUM(A1:A2)'],
            'plus' => ['+SUM(A1:A2)'],
            'minus' => ['-10+20'],
            'at' => ['@SUM(A1:A2)'],
            'leading whitespace' => ['  =SUM(A1:A2)'],
        ];
    }

    public function test_regular_text_is_preserved(): void
    {
        $this->assertSame('Pagamento mensal', CsvFormulaSanitizer::sanitize('Pagamento mensal'));
    }
}

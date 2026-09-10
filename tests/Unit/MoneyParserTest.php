<?php

namespace Tests\Unit;

use App\Services\PublicMoney\MoneyParser;
use PHPUnit\Framework\TestCase;

class MoneyParserTest extends TestCase
{
    public function test_it_preserves_exact_amount_and_currency(): void
    {
        $this->assertSame(['amount' => '17250.0000', 'currency' => 'USD', 'original' => '17,250.00 USD'], MoneyParser::parse('17,250.00 USD'));
        $this->assertSame(['amount' => '538415.0000', 'currency' => 'LBP', 'original' => '538,415 LBP'], MoneyParser::parse('538,415 LBP'));
        $this->assertNull(MoneyParser::parse('Confidential')['amount']);
    }
}

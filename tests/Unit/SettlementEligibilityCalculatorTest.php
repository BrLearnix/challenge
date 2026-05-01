<?php

namespace Tests\Unit;

use App\Support\SettlementEligibilityCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettlementEligibilityCalculatorTest extends TestCase
{
    private SettlementEligibilityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SettlementEligibilityCalculator;
    }

    public function test_cutoff_at_20_45_inclusive_uses_single_business_day_shift(): void
    {
        $paid = Carbon::parse('2026-04-23 20:45:00', SettlementEligibilityCalculator::TIMEZONE);

        $eligible = $this->calculator->firstSettlementEligibleDay($paid);

        $this->assertSame('2026-04-24', $eligible->timezone(SettlementEligibilityCalculator::TIMEZONE)->toDateString());
    }

    public function test_one_second_after_cutoff_uses_double_business_day_shift(): void
    {
        $paid = Carbon::parse('2026-04-23 20:45:01', SettlementEligibilityCalculator::TIMEZONE);

        $eligible = $this->calculator->firstSettlementEligibleDay($paid);

        $this->assertSame('2026-04-27', $eligible->timezone(SettlementEligibilityCalculator::TIMEZONE)->toDateString());
    }

    #[DataProvider('weekendSkipProvider')]
    public function test_friday_before_cutoff_eligible_monday(string $paidLocal, string $expectedEligibleDate): void
    {
        $paid = Carbon::parse($paidLocal, SettlementEligibilityCalculator::TIMEZONE);

        $eligible = $this->calculator->firstSettlementEligibleDay($paid);

        $this->assertSame(
            $expectedEligibleDate,
            $eligible->timezone(SettlementEligibilityCalculator::TIMEZONE)->toDateString(),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function weekendSkipProvider(): array
    {
        return [
            'friday_before_cutoff' => ['2026-04-24 18:00:00', '2026-04-27'],
            'friday_after_cutoff' => ['2026-04-24 21:00:00', '2026-04-28'],
        ];
    }
}

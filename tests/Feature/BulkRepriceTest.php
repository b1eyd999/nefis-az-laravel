<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use Tests\TestCase;

/**
 * Moving every box's price at once.
 *
 * The arithmetic lives on its own so it can be checked here rather than by
 * clicking twenty-seven designs: a percentage that must not drift, an amount
 * that may be negative, and the roundings a shop actually writes.
 */
class BulkRepriceTest extends TestCase
{
    private function at(float $old, string $how, float $value, string $round = 'none'): float
    {
        return ProductResource::repriced($old, $how, $value, $round);
    }

    public function test_a_percentage_moves_the_price(): void
    {
        $this->assertSame(5.39, $this->at(4.90, 'percent', 10));
        $this->assertSame(4.41, $this->at(4.90, 'percent', -10));
        $this->assertSame(4.90, $this->at(4.90, 'percent', 0));
    }

    public function test_an_amount_is_added_or_taken_off(): void
    {
        $this->assertSame(5.40, $this->at(4.90, 'amount', 0.50));
        $this->assertSame(4.40, $this->at(4.90, 'amount', -0.50));
    }

    public function test_set_ignores_what_was_there(): void
    {
        $this->assertSame(6.39, $this->at(4.90, 'set', 6.39));
        $this->assertSame(6.39, $this->at(120.00, 'set', 6.39));
    }

    public function test_the_roundings_a_shop_writes(): void
    {
        $this->assertSame(5.40, $this->at(5.39, 'percent', 0, '0.10'));
        $this->assertSame(5.50, $this->at(5.39, 'percent', 0, '0.50'));
        $this->assertSame(5.00, $this->at(5.39, 'percent', 0, '1'));
        $this->assertSame(4.90, $this->at(5.39, 'percent', 0, '.90'));
        $this->assertSame(4.99, $this->at(5.39, 'percent', 0, '.99'));
        // and the ending nearest the figure, not always the one below it
        $this->assertSame(5.99, $this->at(5.80, 'percent', 0, '.99'));
        $this->assertSame(6.90, $this->at(6.80, 'percent', 0, '.90'));
    }

    public function test_nothing_goes_below_zero(): void
    {
        $this->assertSame(0.0, $this->at(4.90, 'amount', -10));
        $this->assertSame(0.0, $this->at(4.90, 'percent', -500));
    }

    /** Ten per cent on and ten per cent off is not the price you began with. */
    public function test_it_does_not_pretend_a_percentage_is_reversible(): void
    {
        $up = $this->at(4.90, 'percent', 10);
        $this->assertNotSame(4.90, $this->at($up, 'percent', -10));
    }
}

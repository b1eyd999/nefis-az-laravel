<?php

namespace Tests;

use App\Models\Product;
use App\Support\Xonca;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Take the shop's own shelf stock back off the shelf.
     *
     * A migration puts the thirteen xonça designs into the catalogue, which
     * is right for the shop and wrong for a test that counts the designs it
     * created itself, or asks what the page says when there are none. Those
     * tests call this first and then build the catalogue they mean to test.
     *
     * TheXoncaShelfIsStockedTest is the one that does not: it is about the
     * thirteen.
     */
    protected function clearSeededDesigns(): void
    {
        $ids = Product::where('category', Xonca::CATEGORY)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach (['text_slots', 'photo_slots'] as $table) {
            \DB::table($table)
                ->where('slotable_type', Product::class)
                ->whereIn('slotable_id', $ids)
                ->delete();
        }

        Product::whereIn('id', $ids)->delete();
    }
}

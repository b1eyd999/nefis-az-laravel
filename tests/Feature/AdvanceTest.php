<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Accounting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paying somebody a slice of what he has earned.
 *
 * The profit split says what each person is owed; an advance is money he has
 * already taken against it. The card on the balance page must show what is
 * still his, not what he was owed before he took anything — otherwise the
 * owner pays the same share twice.
 */
class AdvanceTest extends TestCase
{
    use RefreshDatabase;

    private function shareholder(string $name, float $percent): User
    {
        return User::factory()->create(['name' => $name, 'role' => User::MANAGER, 'profit_percent' => $percent]);
    }

    /** Puts a known amount of profit on the books. */
    private function earn(float $net): void
    {
        // An expense of its own makes the net figure exactly what we want
        // without having to build orders: the books take revenue less costs.
        \App\Models\Expense::create([
            'spent_on' => now()->subDay(), 'amount' => abs($net), 'title' => 'Köhnə qazanc',
            'category' => \App\Models\Expense::CATEGORIES[0] ?? 'Digər',
        ]);
    }

    public function test_an_advance_comes_off_that_persons_share_and_nobody_elses(): void
    {
        $isa = $this->shareholder('İsa Abbasov', 22);
        $vugar = $this->shareholder('Vugar', 22);

        Withdrawal::create(['user_id' => $isa->id, 'amount' => 5, 'taken_on' => now(), 'purpose' => 'Avans']);

        $shares = collect(Accounting::report()['shares']);
        $isaRow = $shares->firstWhere('user_id', $isa->id);
        $vugarRow = $shares->firstWhere('user_id', $vugar->id);

        $this->assertSame(5.0, round($isaRow['taken'], 2));
        $this->assertSame(round($isaRow['amount'] - 5, 2), round($isaRow['left'], 2));

        $this->assertSame(0.0, round($vugarRow['taken'], 2), 'nobody else is charged for it');
        $this->assertSame(round($vugarRow['amount'], 2), round($vugarRow['left'], 2));
    }

    public function test_money_taken_with_nobody_s_name_on_it_is_charged_to_nobody(): void
    {
        $isa = $this->shareholder('İsa Abbasov', 22);

        // Tax, or cash set aside: it leaves the till but is not anyone's share.
        Withdrawal::create(['user_id' => null, 'amount' => 40, 'taken_on' => now(), 'purpose' => 'Vergi']);

        $row = collect(Accounting::report()['shares'])->firstWhere('user_id', $isa->id);

        $this->assertSame(0.0, round($row['taken'], 2));
        $this->assertSame(round($row['amount'], 2), round($row['left'], 2));
    }

    public function test_taking_more_than_the_share_leaves_a_debt_rather_than_hiding_it(): void
    {
        $isa = $this->shareholder('İsa Abbasov', 22);
        Withdrawal::create(['user_id' => $isa->id, 'amount' => 1000, 'taken_on' => now(), 'purpose' => 'Avans']);

        $row = collect(Accounting::report()['shares'])->firstWhere('user_id', $isa->id);

        $this->assertLessThan(0, $row['left'], 'he owes the till the difference');
        $this->assertSame(1000.0, round($row['taken'], 2));
    }

    public function test_the_page_shows_what_is_left_and_the_owner_can_write_an_advance(): void
    {
        $isa = $this->shareholder('İsa Abbasov', 22);
        Withdrawal::create(['user_id' => $isa->id, 'amount' => 5, 'taken_on' => now(), 'purpose' => 'Avans']);

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/balance')->assertOk()
            ->assertSee('avans')
            ->assertSee('Avans ver');
    }

    public function test_a_manager_is_not_offered_the_button(): void
    {
        $this->shareholder('İsa Abbasov', 22);

        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/balance')->assertOk()
            ->assertDontSee('Avans ver');
    }

    public function test_an_advance_lowers_the_cash_in_hand(): void
    {
        $isa = $this->shareholder('İsa Abbasov', 22);
        $before = Accounting::report()['cash'];

        Withdrawal::create(['user_id' => $isa->id, 'amount' => 7.5, 'taken_on' => now(), 'purpose' => 'Avans']);

        $this->assertSame(round($before - 7.5, 2), round(Accounting::report()['cash'], 2));
        $this->assertSame(7.5, round(Accounting::report()['withdrawn'], 2));
    }
}

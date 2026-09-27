<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * The owner takes the same 22% as the two managers.
 *
 * His own account sat at 0, so the profit split listed the managers and not
 * him. He asked for an equal share; what none of the three takes stays with
 * the shop and the balance page now shows it on its own line.
 *
 * Only an admin who has no share at all is touched, and only when there is
 * exactly one such admin - a percentage someone has already written down is
 * never overwritten by a deploy.
 */
return new class extends Migration
{
    private const SHARE = 22.0;

    public function up(): void
    {
        $admins = User::where('role', User::ADMIN)->where('profit_percent', '<=', 0)->orderBy('id')->get();

        if ($admins->count() !== 1) {
            return;
        }

        $taken = (float) User::where('id', '!=', $admins->first()->id)->sum('profit_percent');

        if ($taken + self::SHARE > 100) {
            return;
        }

        $admins->first()->forceFill(['profit_percent' => self::SHARE])->save();
    }

    public function down(): void
    {
        User::where('role', User::ADMIN)->where('profit_percent', self::SHARE)->orderBy('id')->limit(1)
            ->update(['profit_percent' => 0]);
    }
};

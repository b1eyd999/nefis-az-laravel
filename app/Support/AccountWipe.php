<?php

namespace App\Support;

use App\Models\LivePhoto;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A customer closing his own account.
 *
 * The row is not deleted. `orders.user_id` is NOT NULL and cascades, so
 * deleting the account would delete every order with it — and the books, the
 * stock movements and the e-qaimə numbers all count on those rows still
 * being there. What is deleted is everything personal: on the account, and —
 * this is the part that matters — inside each order, where the address, the
 * phone and the recipient's name actually live.
 *
 * The order keeps its number, its date, its money and its status. It stops
 * saying who it was for and where it went.
 */
class AccountWipe
{
    /** A dead address, kept unique because the column is. */
    public static function deadEmail(User $user): string
    {
        return 'silinmis-' . $user->id . '@nefis.invalid';
    }

    /**
     * Why this account may not be closed from the site, or null when it may.
     *
     * Three refusals, and each is a real one rather than caution:
     * an order still on its way cannot have its address wiped; the shop's own
     * people and its couriers are not customers; and a shareholder's row is
     * what the withdrawals are counted against.
     */
    public static function refusal(User $user): ?string
    {
        if ($user->isStaff()) {
            return __('Mağazanın işçisi hesabı özü silə bilməz — sahibinə yazın.');
        }

        if ($user->isCourier()) {
            return __('Kuryer hesabı özü silə bilməz — sahibinə yazın.');
        }

        if ((float) ($user->profit_percent ?? 0) > 0) {
            return __('Payı olan hesab silinmir — sahibinə yazın.');
        }

        if (static::openOrders($user)->isNotEmpty()) {
            return __('Hazırlanan və ya yolda olan sifarişiniz var. O tamamlananda hesabı silə bilərsiniz.');
        }

        return null;
    }

    /** Orders that are still running, so their address is still needed. */
    public static function openOrders(User $user)
    {
        return $user->orders()
            ->whereNotIn('status', array_merge(['completed'], Order::OFF_THE_BOOKS))
            ->get()
            ->concat($user->orders()->get()->filter->hasOpenAdjustments())
            ->unique('id')
            ->values();
    }

    /**
     * Scrubs the account and everything of his inside the orders.
     *
     * @return bool false when something came up between the check and here
     */
    public static function run(User $user): bool
    {
        if (static::refusal($user) !== null) {
            return false;
        }

        $oldEmail = $user->email;

        DB::transaction(function () use ($user, $oldEmail) {
            // 1. The orders. Their money columns are not touched — only the
            //    lines that say who the box was for and where it went.
            foreach ($user->orders()->get() as $order) {
                $order->forceFill([
                    'contact_phone' => null,
                    'recipient_name' => null,
                    'delivery_address' => null,
                    'postal_index' => null,
                    'metro_station' => null,
                    'delivery_lat' => null,
                    'delivery_lng' => null,
                    'note' => null,
                    'courier_chat_id' => null,
                ])->saveQuietly();

                static::dropReceipt($order);
                static::dropLivePhotos($order);
            }

            // 2. The account itself. The id stays so the orders still hang
            //    off something; everything that names a person goes.
            $user->forceFill([
                'name' => '',
                'email' => static::deadEmail($user),
                // Emptied rather than kept: while the number is here,
                // signing in by it still finds this account, and he could
                // never open a new one on his own number.
                'phone' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'email_verified_at' => null,
                'role' => User::CUSTOMER,
                'is_admin' => false,
                'profit_percent' => 0,
                'sharing_until' => null,
                'scrubbed_at' => now(),
            ])->saveQuietly();

            // 3. What is left of him elsewhere: his basket, the links he was
            //    sent, his place on the courier's map, the password letters
            //    that carry his old address, and every session he is signed
            //    in on — on this phone and on any other.
            DB::table('saved_carts')->where('user_id', $user->id)->delete();
            DB::table('cart_handoffs')->where('user_id', $user->id)->delete();
            DB::table('courier_positions')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            if (filled($oldEmail)) {
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            }
        });

        return true;
    }

    /** The payment slip carries his name and the tail of his card. */
    private static function dropReceipt(Order $order): void
    {
        if (blank($order->payment_receipt)) {
            return;
        }

        try {
            Storage::disk('public')->delete($order->payment_receipt);
        } catch (\Throwable) {
            // A file already gone is not a reason to stop the wipe.
        }

        $order->forceFill(['payment_receipt' => null])->saveQuietly();
    }

    /**
     * His live photos, deleted through the model so its own hook takes the
     * picture and the video off the disk with them.
     *
     * The videos already handed to Yandex Disk stay there: that is the
     * owner's own drive and nothing here can reach into it. The privacy page
     * says so rather than pretending otherwise.
     */
    private static function dropLivePhotos(Order $order): void
    {
        $ids = $order->items()->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        LivePhoto::whereIn('order_item_id', $ids)->get()->each->delete();
    }
}

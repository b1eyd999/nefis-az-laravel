<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer rings up after paying: add a bar, drop the live photo.
 *
 * Until now the shop had no answer for that. The order's money was one
 * number in one place — `payment_asked_for`, `epoint_transaction`,
 * `payment_confirmed_at`, one of each — and the payment handler refuses
 * outright to record a second payment on an order that already has one
 * (EpointController, "a second payment for an order already paid"). So the
 * order itself, and the payment that settled it, are left alone for ever.
 *
 * What is added instead stands beside the order: one row per change, with its
 * own amount, its own reference at the gateway and its own confirmation. The
 * order grew by 5 ₼ and the customer paid it on Tuesday; the order shrank by
 * 5 ₼ and the money went back on Thursday — both are rows here, and the first
 * payment is still exactly where it was.
 *
 * A charge is collected like any other payment: the customer gets a link and
 * pays that amount on the gateway's own page. A refund is only recorded —
 * there is no refund call in the gateway's API, so the owner gives the money
 * back in his ePoint cabinet and ticks it off here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            /** charge: the customer owes more. refund: the shop owes him. */
            $table->string('kind', 16)->default('charge');
            $table->decimal('amount', 10, 2);
            /** What changed, in the owner's own words — the customer reads this. */
            $table->string('reason')->nullable();
            /** waiting → paid, or cancelled if it came to nothing. */
            $table->string('status', 16)->default('waiting');

            // The same payment columns an order carries, so a charge can be
            // settled by card or by transfer exactly as the order itself was.
            $table->string('payment_method')->nullable();
            $table->string('epoint_ref', 40)->nullable()->unique();
            $table->string('epoint_transaction', 64)->nullable();
            $table->decimal('payment_asked_for', 10, 2)->nullable();
            $table->timestamp('payment_started_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->string('payment_receipt')->nullable();
            $table->timestamp('receipt_at')->nullable();

            /** Who changed the order — there is no other history of it. */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};

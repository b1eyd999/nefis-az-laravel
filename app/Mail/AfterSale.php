<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Review;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The letter that follows a handover: thank you, how was it, and a code off
 * the next box.
 *
 * Three things in one letter on purpose. The thanks is the reason to open
 * it, the review is what the shop needs, and the code is what makes it worth
 * the customer's minute — asked for on its own, a review from a chocolate
 * shop is a favour.
 */
class AfterSale extends Mailable
{
    public function __construct(
        public Order $order,
        public ?string $promo = null,
        public float $percent = 0,
        public int $days = 0,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(CustomerNotice::FROM, 'Nefis.az'),
            subject: __('Nefis.az — sifarişiniz üçün təşəkkür edirik'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.after-sale', with: [
            'order' => $this->order,
            'name' => $this->order->user?->name,
            'promo' => $this->promo,
            'percent' => rtrim(rtrim(number_format($this->percent, 2, '.', ''), '0'), '.'),
            'days' => $this->days,
            // Only when he may still write one: a letter that invites him to
            // a page that would 404 is worse than one that says nothing.
            'reviewLink' => Review::invited($this->order, $this->order->user)
                ? CustomerNotice::reviewLink($this->order)
                : null,
            'designs' => CustomerNotice::designsLink($this->order),
        ]);
    }
}

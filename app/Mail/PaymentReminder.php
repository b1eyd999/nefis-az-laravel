<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One reminder to somebody who built a box, sent the order and then left the
 * payment page.
 *
 * Placing the order empties the basket, so a visitor who wandered off found
 * nothing anywhere and often thought the whole thing had failed. The letter
 * says where his order is and takes him straight back to it.
 *
 * One per order, ever: a reminder sent every hour is a reason to block the
 * sender.
 */
class PaymentReminder extends Mailable
{
    public function __construct(public Order $order, public int $hoursLeft)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(CustomerNotice::FROM, 'Nefis.az'),
            subject: __('Nefis.az — sifarişiniz #:id ödəniş gözləyir', ['id' => $this->order->id]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-reminder', with: [
            'order' => $this->order,
            'name' => $this->order->user?->name,
            'hoursLeft' => $this->hoursLeft,
            'link' => CustomerNotice::link($this->order),
            'total' => \App\Support\Price::format($this->order->total()),
        ]);
    }
}

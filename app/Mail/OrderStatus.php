<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The letter a customer gets when his order moves: what happened, when the
 * box is due, and where to look at the order itself.
 */
class OrderStatus extends Mailable
{
    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(CustomerNotice::FROM, 'Nefis.az'),
            subject: __('Nefis.az — sifariş #') . $this->order->id . ': ' . __($this->order->statusLabel()),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-status', with: [
            'order' => $this->order,
            'line' => CustomerNotice::line($this->order),
            'link' => CustomerNotice::link($this->order),
        ]);
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A letter the owner writes himself from the order's page — the e-mail
 * counterpart of the WhatsApp button beside it. The words are his; the shop's
 * name, the order's number and the way back to it are added around them.
 */
class OrderMessage extends Mailable
{
    public function __construct(
        public Order $order,
        public string $subjectLine,
        public string $body,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(CustomerNotice::FROM, 'Nefis.az'),
            replyTo: [new \Illuminate\Mail\Mailables\Address(CustomerNotice::FROM, 'Nefis.az')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-message', with: [
            'order' => $this->order,
            'body' => $this->body,
            'link' => CustomerNotice::link($this->order),
        ]);
    }
}

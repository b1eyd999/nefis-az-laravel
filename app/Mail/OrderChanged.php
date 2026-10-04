<?php

namespace App\Mail;

use App\Models\OrderAdjustment;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The letter a customer gets when his order is changed after he has paid for
 * it: what changed, what it comes to, and either where to pay the difference
 * or that the money is on its way back to him.
 */
class OrderChanged extends Mailable
{
    public function __construct(public OrderAdjustment $adjustment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(CustomerNotice::FROM, 'Nefis.az'),
            subject: __('Nefis.az, sifariş #') . $this->adjustment->order_id . ': '
                . ($this->adjustment->isCharge() ? __('əlavə ödəniş') : __('məbləğ qaytarılır')),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-changed', with: [
            'order' => $this->adjustment->order,
            'adjustment' => $this->adjustment,
            'link' => CustomerNotice::adjustmentLink($this->adjustment),
        ]);
    }
}

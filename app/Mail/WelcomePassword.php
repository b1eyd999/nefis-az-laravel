<?php

namespace App\Mail;

use App\Models\User;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The letter to a customer the shop made an account for while he was
 * ordering: here is your account, and here is how to get into it.
 *
 * The same link a forgotten password sends, said the other way round: he is
 * not recovering anything, he is setting a password for the first time. It
 * lasts three days rather than an hour, because nobody is waiting for it —
 * he will look for it when he next wants to see his order.
 */
class WelcomePassword extends Mailable
{
    public function __construct(public User $user, public string $token, public int $hours)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(CustomerNotice::FROM, 'Nefis.az'),
            subject: __('Nefis.az — hesabınız hazırdır'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welcome-password', with: [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'hours' => $this->hours,
            'days' => (int) round($this->hours / 24),
            'link' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email]),
            'orders' => route('orders.index'),
        ]);
    }
}

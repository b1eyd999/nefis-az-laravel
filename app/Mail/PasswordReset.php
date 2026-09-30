<?php

namespace App\Mail;

use App\Models\User;
use App\Support\CustomerNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The letter with the link that sets a new password. One link, one hour.
 */
class PasswordReset extends Mailable
{
    public function __construct(public User $user, public string $token, public int $hours)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(CustomerNotice::FROM, 'Nefis.az'),
            subject: __('Nefis.az — şifrənin yenilənməsi'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset', with: [
            'name' => $this->user->name,
            'hours' => $this->hours,
            'link' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email]),
        ]);
    }
}

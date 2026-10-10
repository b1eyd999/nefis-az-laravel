<?php

namespace App\Support;

use App\Mail\WelcomePassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Ordering without signing up first.
 *
 * The shop lost people at the sign-up: a visitor who had chosen a box, put
 * his photograph on it and written his words was then asked to invent a
 * password before he could pay. He is asked for his name, his number and his
 * e-mail — the three things the order needs anyway — and the account is made
 * for him out of those.
 *
 * It is a real account, not a stranger's order: his orders are his, the
 * payment page is his, the basket is kept for him, and the letter that
 * follows carries a link for setting a password whenever he wants one. What
 * he is never asked is to think of one before he has bought anything.
 */
class GuestCheckout
{
    /** How long the link in the welcome letter is good for, in hours. */
    public const HOURS = 72;

    /**
     * The rules for the three fields a guest fills in, or nothing at all
     * when he is already signed in.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'min:2', 'max:255'],
            'guest_email' => ['required', 'string', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'guest_name.required' => __('Adınızı yazın.'),
            'guest_email.required' => __('E-poçtunuzu yazın — sifariş haqqında oraya yazacağıq.'),
            'guest_email.email' => __('E-poçt düzgün görünmür.'),
            'guest_phone.required' => __('Əlaqə nömrənizi yazın.'),
        ];
    }

    /**
     * Whoever is ordering: the customer already signed in, or a new account
     * made from what he has just typed.
     *
     * An address or a number the shop already knows is not quietly taken
     * over — that would hand one person's order history to whoever typed his
     * e-mail. He is told he has an account and sent to the door, and his
     * basket is still there when he comes back.
     */
    public static function who(Request $request): User
    {
        if ($user = $request->user()) {
            return $user;
        }

        $data = $request->validate(self::rules(), self::messages());

        $phone = Contact::az($data['guest_phone']);
        if (! $phone) {
            self::refuse('guest_phone', __('Telefon nömrəsini +994 55 555 55 55 şəklində yazın.'));
        }

        $email = Str::lower(trim($data['guest_email']));

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            self::refuse('guest_email', __('Bu e-poçtla hesabınız var. Daxil olun — səbətiniz yerindədir.'));
        }

        if (User::samePhone($phone)->exists()) {
            self::refuse('guest_phone', __('Bu nömrə ilə hesabınız var. Daxil olun — səbətiniz yerindədir.'));
        }

        /* A password he never chose and never needs to know. The letter that
           follows is how he gets in, and until he does, the session he is
           sitting in is his way through this order. */
        $user = User::create([
            'name' => trim($data['guest_name']),
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make(Str::random(40)),
        ]);

        Telegram::signedUp($user);

        Auth::login($user);
        $request->session()->regenerate();
        // The basket was built before there was anybody to keep it for.
        Cart::keep();

        self::welcome($user);

        return $user;
    }

    /**
     * The letter that says the account exists and how to get into it.
     *
     * Sent on the same machinery as a forgotten password, because it is the
     * same thing from the customer's side: a link that lets him set one. It
     * lasts longer than an hour — he is not waiting for it, he will find it
     * when he looks for his order.
     */
    public static function welcome(User $user): void
    {
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        try {
            Mail::mailer(CustomerNotice::mailer())->to($user->email)
                ->send(new WelcomePassword($user, $token, self::HOURS));
        } catch (\Throwable $e) {
            // A letter that will not send must not cost the order.
            Log::warning('guest checkout: the welcome letter did not go', [
                'user' => $user->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Back to the checkout with this said against that field.
     *
     * Thrown rather than returned, so the three checks above read as the
     * rules they are and no caller can go on with no customer. It is the
     * same failure the validator itself raises, so what the page does with
     * it — the message under the field, everything typed still in place —
     * needs no code of its own.
     */
    private static function refuse(string $field, string $says): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([$field => $says]);
    }
}

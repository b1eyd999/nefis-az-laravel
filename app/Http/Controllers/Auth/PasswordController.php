<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * A forgotten password.
 *
 * The customer asks by e-mail, gets a link that is good for an hour, and
 * sets a new password with it. Two things are deliberate:
 *
 *  - the page never says whether an address is registered. Told "no such
 *    customer", anyone could check who shops here;
 *  - a customer who signed up by phone has no e-mail to write to, so the
 *    page says to write to the shop rather than pretending a letter is on
 *    its way.
 */
class PasswordController extends Controller
{
    /** How long a link is good for, in minutes. */
    private const HOURS = 1;

    public function showRequest(): View
    {
        return view('auth.forgot');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [], ['email' => __('E-poçt')]);

        $email = Str::lower(trim($data['email']));

        // Nobody is sent a hundred letters, and nobody may use this to find
        // out which addresses the shop knows.
        $key = 'reset:' . $email . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'email' => __('Çox cəhd oldu. :seconds saniyə sonra yenidən yoxlayın.',
                    ['seconds' => RateLimiter::availableIn($key)]),
            ])->onlyInput('email');
        }
        RateLimiter::hit($key, 900);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            Mail::to($user->email)->send(new PasswordReset($user, $token, self::HOURS));
        }

        /* The same answer either way. */
        return back()->with('status', __('Əgər bu e-poçt bizdə varsa, şifrəni yeniləmək üçün məktub göndərdik. Poçtunuzu yoxlayın.'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], ['email' => __('E-poçt'), 'password' => __('Şifrə')]);

        $email = Str::lower(trim($data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $row = $user ? DB::table('password_reset_tokens')->where('email', $user->email)->first() : null;

        $good = $row
            && Hash::check($data['token'], $row->token)
            && now()->diffInMinutes($row->created_at, true) <= self::HOURS * 60;

        if (! $good) {
            return back()->withErrors([
                'email' => __('Bu keçid artıq işləmir. Yeni keçid istəyin.'),
            ])->onlyInput('email');
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Signed in straight away: he has just proved the address is his.
        Auth::login($user);
        $request->session()->regenerate();

        /* And whoever else was in the account is put out. A password is reset
           because somebody is where he should not be; leaving his session
           alive makes the reset a gesture. */
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return redirect()->intended(lroute('home'))->with('status', __('Şifrəniz yeniləndi.'));
    }
}

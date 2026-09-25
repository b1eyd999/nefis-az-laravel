<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // The shop rings and writes on WhatsApp; an order without a number
            // is an order nobody can ask about.
            'phone' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) {
                if (! Contact::az($value)) {
                    $fail(__('Telefon nömrəsini +994 55 555 55 55 şəklində yazın.'));
                }
            }],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => Contact::az($data['phone']),
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(lroute('home'))->with('status', __('Qeydiyyat uğurla tamamlandı, xoş gəldiniz!'));
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['login' => __('E-poçt və ya telefon')]);

        // A password is not guessed by hand: after five tries the door waits.
        $key = 'login:' . Str::lower($data['login']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'login' => __('Çox cəhd oldu. :seconds saniyə sonra yenidən yoxlayın.',
                    ['seconds' => RateLimiter::availableIn($key)]),
            ])->onlyInput('login');
        }

        $user = User::byLogin($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'login' => __('Daxil etdiyiniz məlumatlar yanlışdır.'),
            ])->onlyInput('login');
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(lroute('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(lroute('home'));
    }
}

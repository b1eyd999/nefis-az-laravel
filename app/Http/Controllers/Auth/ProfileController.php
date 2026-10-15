<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountWipe;
use App\Support\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The customer's own page: his name, the number the shop rings, his e-mail
 * and his password.
 *
 * Until now a customer could sign in and see his orders and nothing else —
 * a number typed wrong at the sign-up stayed wrong, and the only way to a
 * new password was to say you had forgotten the old one.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.profile', ['user' => $request->user()]);
    }

    /** His name, his number, his e-mail. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            // The shop rings and writes on WhatsApp, and two accounts on one
            // number can never sign in by it again.
            'phone' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) use ($user) {
                if (! Contact::az($value)) {
                    $fail(__('Telefon nömrəsini +994 55 555 55 55 şəklində yazın.'));
                } elseif (User::samePhone($value)->whereKeyNot($user->id)->exists()) {
                    $fail(__('Bu nömrə ilə artıq hesab var.'));
                }
            }],
        ], [], [
            'name' => __('Ad Soyad'),
            'email' => __('E-poçt'),
            'phone' => __('Telefon'),
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => Contact::az($data['phone']),
        ]);

        return back()->with('status', __('Məlumatlarınız yadda saxlanıldı.'));
    }

    /**
     * A new password, with the old one asked for first — somebody else at
     * the same telephone must not be able to lock the owner of it out.
     */
    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8)],
        ], [
            'current_password.current_password' => __('Hazırkı şifrə düzgün deyil.'),
        ]);

        $request->user()->update(['password' => Hash::make($request->string('password')->toString())]);

        return back()->with('status', __('Şifrəniz dəyişdirildi.'));
    }

    /**
     * Closing the account, from the account itself.
     *
     * The word has to be typed out rather than a password given: an account
     * made at checkout never had a password chosen for it, and its owner has
     * as much right to close it as anyone.
     *
     * What this does is written out in App\Support\AccountWipe — in short,
     * the row stays so the orders still add up in the books, and everything
     * personal on it and inside those orders goes.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($refusal = AccountWipe::refusal($user)) {
            return back()->withErrors(['wipe' => $refusal]);
        }

        $request->validate([
            'confirm' => ['required', 'string', function ($attribute, $value, $fail) {
                if (Str::upper(trim((string) $value)) !== Str::upper(__('SİL'))) {
                    $fail(__('Təsdiq üçün :word yazın.', ['word' => __('SİL')]));
                }
            }],
        ]);

        if (! AccountWipe::run($user)) {
            return back()->withErrors(['wipe' => __('Hesab silinmədi. Bir az sonra yenidən yoxlayın.')]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(lroute('home'))->with('status', __('Hesabınız silindi. Şəxsi məlumatlarınız saytdan getdi.'));
    }
}

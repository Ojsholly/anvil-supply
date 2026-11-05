<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Minimal session login — exists so Passport's authorize endpoint has
 * somewhere to send guests. This is the "human" half of the OAuth dance:
 * the agent brings a bearer token; the person behind it signs in here once.
 */
class LoginController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Wrong credentials.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Passport parked the intended /oauth/authorize URL in the session —
        // redirect()->intended() sends the freshly-signed-in user straight
        // back to the consent screen.
        return redirect()->intended('/');
    }
}

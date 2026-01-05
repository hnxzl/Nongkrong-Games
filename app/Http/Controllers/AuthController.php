<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class AuthController extends Controller
{
    /**
     * Tampilkan form login/register
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Login sebagai guest (tanpa email/password)
     * Untuk kemudahan bermain di coffee shop
     */
    public function guestLogin(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50|min:2',
            ]);

            // Buat user guest
            $user = User::create([
                'name' => $validated['name'],
                'email' => 'guest_' . Str::random(10) . '@tongkrongan.games',
                'password' => Hash::make(Str::random(16)),
            ]);

            Auth::login($user);

            return redirect()->intended(route('home'));
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Gagal login: ' . $e->getMessage()]);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

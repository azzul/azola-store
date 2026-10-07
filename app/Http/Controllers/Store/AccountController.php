<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Akun pembeli: daftar, masuk, pesanan saya, dan profil. Dashboard admin punya pintu masuk sendiri. */
class AccountController extends Controller
{
    private function seo(string $title): array
    {
        return Seo::page(['title' => $title, 'robots' => 'noindex,nofollow']);
    }

    public function loginForm(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('account.home');
        }

        return view('store.account.login', ['seo' => $this->seo('Masuk')]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'customer-login:'.strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('account.home'));
    }

    public function registerForm()
    {
        if (Auth::check()) {
            return redirect()->route('account.home');
        }

        return view('store.account.register', ['seo' => $this->seo('Daftar')]);
    }

    public function register(Request $request)
    {
        if (filled($request->input('website'))) { // honeypot
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->phone = $data['phone'] ?? null;
        $user->withRole(User::ROLE_CUSTOMER)->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.home'))->with('ok', 'Akun dibuat. Selamat datang, '.$user->name.'.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function home(Request $request)
    {
        $user = $request->user();

        return view('store.account.home', [
            'seo' => $this->seo('Akun saya'),
            'user' => $user,
            'recent' => $user->orders()->latest('ordered_at')->limit(3)->get(),
            'orderCount' => $user->orders()->count(),
        ]);
    }

    public function orders(Request $request)
    {
        return view('store.account.orders', [
            'seo' => $this->seo('Pesanan saya'),
            'orders' => $request->user()->orders()->withCount('items')->latest('ordered_at')->paginate(10),
            'methods' => config('store.web_payment_methods'),
        ]);
    }

    public function profile(Request $request)
    {
        return view('store.account.profile', ['seo' => $this->seo('Profil'), 'user' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ], ['phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.']);

        $user->forceFill($data)->save();

        return back()->with('ok', 'Profil disimpan.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['current_password.current_password' => 'Password saat ini salah.', 'password.confirmed' => 'Konfirmasi password tidak sama.']);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('ok', 'Password diganti.');
    }
}

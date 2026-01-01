<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ExpiredPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.auth.password-expired', [
            'username' => $request->user('admin')?->username,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('admin');

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        if ($user && Hash::check($validated['password'], $user->password)) {
            return back()->withErrors([
                'password' => 'Please choose a new password that is different from your current one.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'last_password_changed_at' => now(),
        ])->save();
        $user = $user->fresh();

        Auth::guard('admin')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'))
            ->with('status', 'Password updated successfully.');
    }
}

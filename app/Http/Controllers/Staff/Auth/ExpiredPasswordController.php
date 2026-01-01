<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffUser;
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
        return view('staff.auth.password-expired', [
            'email' => $request->user('staff')?->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('staff');

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

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('staff.dashboard'))
            ->with('status', 'Password updated successfully.');
    }
}

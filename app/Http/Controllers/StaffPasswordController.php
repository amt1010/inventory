<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffPasswordController extends Controller
{
    public function edit(): View
    {
        return view('staff.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $staff = $request->user('staff');

        $staff->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        return redirect('/admin');
    }
}

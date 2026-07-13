<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetSellerPasswordRequest;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function show(Request $request, Seller $seller): View
    {
        if ($seller->status !== 'pending_email_verification') {
            return view('seller.activation-invalid');
        }

        if ($seller->created_by === 'admin') {
            return view('seller.set-password', ['seller' => $seller]);
        }

        $seller->update([
            'email_verified_at' => now(),
            'status' => 'pending_admin_approval',
        ]);

        return view('seller.activation-complete', ['seller' => $seller]);
    }

    public function store(SetSellerPasswordRequest $request, Seller $seller): View
    {
        if ($seller->status !== 'pending_email_verification' || $seller->created_by !== 'admin') {
            return view('seller.activation-invalid');
        }

        $seller->update([
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return view('seller.activation-complete', ['seller' => $seller]);
    }
}

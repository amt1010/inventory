<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClerkPanelLoginController extends Controller
{
    public function store(Request $request, ClerkAuthenticator $clerk): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $identity = $clerk->identify($request->string('token')->toString());

        $seller = Seller::where('clerk_user_id', $identity->id)->first();

        if (! $seller) {
            return response()->json([
                'error' => 'No seller account is linked to this Google account. Register as a seller first.',
            ], 422);
        }

        if (! $seller->isApproved()) {
            return response()->json([
                'error' => 'Your seller account is still awaiting approval.',
            ], 422);
        }

        Auth::guard('seller')->login($seller);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('filament.seller.pages.dashboard')]);
    }
}

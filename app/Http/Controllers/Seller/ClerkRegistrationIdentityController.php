<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClerkRegistrationIdentityController extends Controller
{
    public function store(Request $request, ClerkAuthenticator $clerk): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $identity = $clerk->identify($request->string('token')->toString());

        if (Seller::where('email', $identity->email)->exists()) {
            return response()->json([
                'error' => 'A seller account already exists for this email. Try logging in instead.',
            ], 422);
        }

        $request->session()->put('seller_clerk_identity', [
            'id' => $identity->id,
            'email' => $identity->email,
            'name' => $identity->name,
        ]);

        return response()->json(['redirect' => route('seller.register')]);
    }
}

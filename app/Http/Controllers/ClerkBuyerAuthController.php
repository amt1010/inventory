<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Clerk\ClerkAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClerkBuyerAuthController extends Controller
{
    public function store(Request $request, ClerkAuthenticator $clerk): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $identity = $clerk->identify($request->string('token')->toString());

        $user = User::where('clerk_user_id', $identity->id)->first()
            ?? User::where('email', $identity->email)->first()
            ?? new User(['email' => $identity->email]);

        $user->clerk_user_id = $identity->id;

        if (blank($user->name)) {
            $user->name = $identity->name ?? $identity->email;
        }

        $user->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('home')]);
    }
}

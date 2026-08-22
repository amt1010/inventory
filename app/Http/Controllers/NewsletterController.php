<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Rules\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        Subscriber::query()->firstOrCreate(['email' => $validated['email']]);

        return back()->with('newsletter_subscribed', true);
    }
}

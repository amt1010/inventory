<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        Subscriber::query()->firstOrCreate(['email' => $validated['email']]);

        return back()->with('newsletter_subscribed', true);
    }
}

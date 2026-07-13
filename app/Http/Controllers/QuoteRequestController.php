<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequestRequest $request): RedirectResponse
    {
        $quoteRequest = QuoteRequest::create([
            ...$request->safe()->except(['privacy_policy', 'g-recaptcha-response']),
            'user_id' => auth('web')->id(),
            'source_url' => $request->input('source_url'),
            'status' => 'new',
        ]);

        try {
            Mail::to(config('rfq.notification_email'))->send(new QuoteRequestReceived($quoteRequest));
        } catch (\Throwable $exception) {
            Log::error('Failed to send quote request notification email.', [
                'quote_request_id' => $quoteRequest->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return back()->with('quote_request_submitted', true);
    }
}

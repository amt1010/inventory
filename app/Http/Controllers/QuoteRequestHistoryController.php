<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use Illuminate\View\View;

class QuoteRequestHistoryController extends Controller
{
    public function index(): View
    {
        $quoteRequests = QuoteRequest::query()
            ->where('user_id', auth('web')->id())
            ->with('product')
            ->latest()
            ->get();

        return view('quote-requests.index', ['quoteRequests' => $quoteRequests]);
    }
}

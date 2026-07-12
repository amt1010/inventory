<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = (string) $request->query('q', '');

        $results = $query !== ''
            ? Product::search($query)->where('status', 'published')->get()
            : collect();

        return view('catalog.search', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}

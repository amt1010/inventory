<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function show(string $slug = 'home'): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        abort_if(! $page, Response::HTTP_NOT_FOUND);

        return view('pages.show', ['page' => $page]);
    }
}

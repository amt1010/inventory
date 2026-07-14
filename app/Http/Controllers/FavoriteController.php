<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(): View
    {
        $favorites = Favorite::query()
            ->where('user_id', auth('web')->id())
            ->whereHas('product', fn ($query) => $query->where('status', 'published'))
            ->with('product.images')
            ->get();

        return view('favorites.index', ['favorites' => $favorites]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['product_id' => ['required', 'exists:products,id']]);

        Favorite::query()->firstOrCreate([
            'user_id' => auth('web')->id(),
            'product_id' => $request->input('product_id'),
        ]);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        Favorite::query()
            ->where('user_id', auth('web')->id())
            ->where('product_id', $product->id)
            ->delete();

        return back();
    }
}

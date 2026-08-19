<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestController extends Controller
{
    private const MAX_RESULTS = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $categories = Category::query()
            ->where('status', 'published')
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (Category $category) => [
                'label' => $category->name,
                'url' => '/products/'.$category->path(),
            ]);

        $products = Product::query()
            ->where('status', 'published')
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (Product $product) => [
                'label' => $product->name,
                'url' => '/products/'.$product->path(),
            ]);

        $suggestions = $categories->concat($products)->take(self::MAX_RESULTS)->values();

        return response()->json($suggestions);
    }
}

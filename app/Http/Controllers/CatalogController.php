<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    public function show(Request $request, string $path = ''): View|\Illuminate\Http\Response
    {
        $segments = array_values(array_filter(explode('/', $path)));

        $breadcrumb = [];
        $parentId = null;
        $category = null;

        foreach ($segments as $index => $segment) {
            $category = Category::query()
                ->where('parent_id', $parentId)
                ->where('slug', $segment)
                ->where('status', 'published')
                ->first();

            if ($category) {
                $breadcrumb[] = $category;
                $parentId = $category->id;

                continue;
            }

            $isLastSegment = $index === array_key_last($segments);

            if ($isLastSegment && $parentId !== null) {
                $product = Product::query()
                    ->where('category_id', $parentId)
                    ->where('slug', $segment)
                    ->where('status', 'published')
                    ->first();

                if ($product) {
                    return view('catalog.product', [
                        'product' => $product,
                        'breadcrumb' => $breadcrumb,
                    ]);
                }
            }

            abort(Response::HTTP_NOT_FOUND);
        }

        $products = $category
            ? $category->products()->where('status', 'published')->orderBy('sort_order')->paginate(9)
            : collect();

        if ($request->ajax()) {
            return response()->view('catalog.partials.product-grid-items', [
                'products' => $products,
                'breadcrumb' => $breadcrumb,
            ])->header('X-Next-Page-Url', $products instanceof \Illuminate\Contracts\Pagination\Paginator ? (string) $products->nextPageUrl() : '');
        }

        return view('catalog.category', [
            'category' => $category,
            'breadcrumb' => $breadcrumb,
            'children' => $category
                ? $category->children()->where('status', 'published')->get()
                : Category::query()->whereNull('parent_id')->where('status', 'published')->orderBy('sort_order')->get(),
            'products' => $products,
        ]);
    }
}

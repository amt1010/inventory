<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CustomAttribute;
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

        $products = collect();
        $filterGroups = collect();

        if ($category) {
            $filterGroups = CustomAttribute::query()
                ->whereHasMorph('attributable', [Product::class], function ($query) use ($category) {
                    $query->where('category_id', $category->id)->where('status', 'published');
                })
                ->get(['label', 'value'])
                ->groupBy('label')
                ->map(fn ($group) => $group->pluck('value')->unique()->sort()->values());

            $productsQuery = $category->products()->with('images')->where('status', 'published');

            foreach ((array) $request->query('attr', []) as $label => $values) {
                $productsQuery->whereHas('customAttributes', function ($query) use ($label, $values) {
                    $query->where('label', $label)->whereIn('value', (array) $values);
                });
            }

            $productsQuery = match ($request->query('sort')) {
                'newest' => $productsQuery->orderBy('created_at', 'desc'),
                'name_asc' => $productsQuery->orderBy('name'),
                'name_desc' => $productsQuery->orderBy('name', 'desc'),
                default => $productsQuery->orderBy('sort_order'),
            };

            $products = $productsQuery->paginate(9)->withQueryString();
        }

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
            'filterGroups' => $filterGroups,
        ]);
    }
}

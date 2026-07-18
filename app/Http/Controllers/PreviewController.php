<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Staff-only preview of catalog pages, rendering the same public views but
 * bypassing the published-status filter so staff can review a product or
 * category before it goes live. Guarded by the `staff` guard in routes/web.php.
 */
class PreviewController extends Controller
{
    public function product(Product $product): View
    {
        return view('catalog.product', [
            'product' => $product->load('images'),
            'breadcrumb' => $this->breadcrumb($product->category),
            'preview' => true,
        ]);
    }

    public function category(Category $category): View
    {
        return view('catalog.category', [
            'category' => $category,
            'breadcrumb' => $this->breadcrumb($category),
            'children' => $category->children()->orderBy('sort_order')->get(),
            'products' => $category->products()->orderBy('sort_order')->get(),
            'preview' => true,
        ]);
    }

    /**
     * Ancestor chain (root → given category, inclusive) for breadcrumbs.
     *
     * @return array<int, Category>
     */
    private function breadcrumb(?Category $category): array
    {
        $crumbs = [];

        while ($category) {
            array_unshift($crumbs, $category);
            $category = $category->parent;
        }

        return $crumbs;
    }
}

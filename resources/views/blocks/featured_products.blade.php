{{-- resources/views/blocks/featured_products.blade.php --}}
@php
    $productOrder = array_flip($data['product_ids'] ?? []);
    $products = \App\Models\Product::with('images')
        ->whereIn('id', $data['product_ids'] ?? [])
        ->where('status', 'published')
        ->get()
        ->sortBy(fn ($product) => $productOrder[$product->id] ?? PHP_INT_MAX)
        ->values();
@endphp
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        @if (!empty($data['heading']))
            <h2 class="mb-0">{{ $data['heading'] }}</h2>
        @endif
        <a href="{{ url('/products') }}" class="md-btn md-btn-ghost">View all products</a>
    </div>
    <div class="row row-cols-1 row-cols-md-4 g-4">
        @foreach ($products as $product)
            <div class="col">
                <a href="{{ url('/products/'.$product->path()) }}" class="md-card h-100 text-decoration-none d-block">
                    <x-product-thumbnail :path="optional($product->primaryImage())->path" :alt="$product->name" />
                    <div class="p-3">
                        <h5 class="mb-1" style="color: var(--color-text);">{{ $product->name }}</h5>
                        @if ($product->quantity)
                            <div class="small text-muted">MOQ: {{ $product->quantity }}</div>
                        @endif
                        @if ($product->price_display)
                            <div class="fw-bold" style="color: var(--color-accent-700);">{{ $product->price_display }}</div>
                        @endif
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>

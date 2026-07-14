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
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    <div class="row row-cols-1 row-cols-md-3 g-4">
        @foreach ($products as $product)
            <div class="col">
                <a href="{{ url('/products/'.$product->path()) }}" class="card h-100 text-decoration-none">
                    @if ($product->images->first())
                        <img src="{{ asset('storage/'.$product->images->first()->path) }}" class="card-img-top" alt="{{ $product->name }}">
                    @endif
                    <div class="card-body">
                        <h5 class="card-title text-dark">{{ $product->name }}</h5>
                        <p class="card-text text-muted">{{ $product->short_description }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>

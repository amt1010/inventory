{{-- resources/views/catalog/partials/product-grid-items.blade.php --}}
@foreach ($products as $product)
    <div class="col">
        <div class="md-card h-100 d-flex flex-column">
            <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($product->slug)->implode('/')) }}" class="text-decoration-none d-block">
                <div class="md-grayscale">
                    <x-product-thumbnail :path="optional($product->primaryImage())->path" :alt="$product->name" />
                </div>
                <div class="p-3 pb-0">
                    <h5 class="mb-1" style="color: var(--color-text);">{{ $product->name }}</h5>
                    @if ($product->quantity)
                        <div class="small text-muted">MOQ: {{ $product->quantity }}</div>
                    @endif
                    @if ($product->price_display)
                        <div class="fw-bold" style="color: var(--color-accent-700);">{{ $product->price_display }}</div>
                    @endif
                </div>
            </a>
            <div class="p-3 pt-2 mt-auto">
                <button type="button" class="md-btn md-btn-primary md-btn-block" data-bs-toggle="modal" data-bs-target="#quoteRequestModal-{{ $product->id }}">Add to RFQ</button>
            </div>
        </div>
    </div>
    @include('partials.quote-request-form', ['product' => $product])
@endforeach

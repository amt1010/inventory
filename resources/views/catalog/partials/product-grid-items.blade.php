{{-- resources/views/catalog/partials/product-grid-items.blade.php --}}
@foreach ($products as $product)
    <div class="col">
        <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($product->slug)->implode('/')) }}" class="card product-card text-decoration-none">
            @if ($product->images->first())
                <img src="{{ asset('storage/'.$product->images->first()->path) }}" class="card-img-top product-card-img" alt="{{ $product->name }}">
            @endif
            <div class="card-body">
                <h5 class="card-title text-dark product-card-title">{{ $product->name }}</h5>
                <p class="card-text text-muted product-card-desc">{{ $product->short_description }}</p>
            </div>
        </a>
    </div>
@endforeach

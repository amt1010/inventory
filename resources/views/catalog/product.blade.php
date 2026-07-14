@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/products') }}">Home</a></li>
            @foreach ($breadcrumb as $crumb)
                <li class="breadcrumb-item">
                    <a href="{{ url('/products/'.collect($breadcrumb)->take($loop->iteration)->pluck('slug')->implode('/')) }}">{{ $crumb->name }}</a>
                </li>
            @endforeach
            <li class="breadcrumb-item active">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-6">
            @foreach ($product->images as $image)
                <img src="{{ asset('storage/'.$image->path) }}" class="img-fluid mb-2" alt="{{ $product->name }}">
            @endforeach
        </div>
        <div class="col-md-6">
            <h1>{{ $product->name }}</h1>

            @if ($product->price_display)
                <p class="fs-4 text-primary">{{ $product->price_display }}</p>
            @endif

            @if (! empty($product->features))
                <h5>Features</h5>
                <ul>
                    @foreach ($product->features as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($product->applications))
                <h5>Applications</h5>
                <ul>
                    @foreach ($product->applications as $application)
                        <li>{{ $application }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($product->spec_sheet_path)
                <a href="{{ asset('storage/'.$product->spec_sheet_path) }}" class="btn btn-outline-danger">Download Specification Sheet</a>
            @endif

            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#quoteRequestModal-{{ $product->id }}">Get a Quote</button>

            @auth('web')
                @if (auth('web')->user()->favorites()->where('product_id', $product->id)->exists())
                    <form method="POST" action="{{ route('favorites.destroy', $product) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger mt-3">Remove Favorite</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('favorites.store') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="btn btn-outline-secondary mt-3">Add to Favorites</button>
                    </form>
                @endif
            @endauth
        </div>
    </div>

    <hr class="my-4">

    <div>
        <h5>Product Description</h5>
        <div>{!! $product->description !!}</div>
    </div>

    @php
        $related = \App\Models\Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'published')
            ->limit(4)
            ->get();
    @endphp

    @if ($related->isNotEmpty())
        <h5 class="mt-4">Related Products</h5>
        <div class="row row-cols-1 row-cols-md-4 g-4">
            @foreach ($related as $relatedProduct)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">{{ $relatedProduct->name }}</h6>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @include('partials.quote-request-form', ['product' => $product])
@endsection

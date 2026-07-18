@extends('layouts.app')

@section('title', $product->name)

@section('content')
    @if ($preview ?? false)
        <div class="alert alert-warning">Staff preview — this page may not be publicly visible yet.</div>
    @endif
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
            @php
                $primaryImage = $product->primaryImage();
                $orderedImages = $product->images->isNotEmpty()
                    ? $product->images->sortBy(fn ($image) => $primaryImage && $image->is($primaryImage) ? 0 : 1)->values()
                    : $product->images;
            @endphp
            @if ($orderedImages->isNotEmpty())
                <div id="productImagesCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
                    <div class="carousel-inner rounded-3 bg-light">
                        @foreach ($orderedImages as $image)
                            <div class="carousel-item @if ($loop->first) active @endif">
                                <img src="{{ asset('storage/'.$image->path) }}" class="d-block w-100" style="height: 480px; object-fit: contain;" alt="{{ $product->name }}">
                            </div>
                        @endforeach
                    </div>
                    @if ($orderedImages->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#productImagesCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productImagesCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    @endif
                </div>
                @if ($orderedImages->count() > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ($orderedImages as $image)
                            <button type="button" data-bs-target="#productImagesCarousel" data-bs-slide-to="{{ $loop->index }}" class="btn p-0 border-0 bg-transparent">
                                <x-product-thumbnail :path="$image->path" :alt="$product->name" />
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
        <div class="col-md-6">
            <h1>{{ $product->name }}</h1>

            @if ($product->price_display)
                <p class="fs-4 text-primary">{{ $product->price_display }}</p>
            @endif

            @if (filled($product->features))
                <h5>Features</h5>
                <div>{!! $product->features !!}</div>
            @endif

            @if (filled($product->applications))
                <h5>Applications</h5>
                <div>{!! $product->applications !!}</div>
            @endif

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                @if ($product->spec_sheet_path)
                    <a href="{{ asset('storage/'.$product->spec_sheet_path) }}" class="btn btn-outline-danger">Download Specification Sheet</a>
                @endif

                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quoteRequestModal-{{ $product->id }}">Get a Quote</button>

                @auth('web')
                    @if (auth('web')->user()->favorites()->where('product_id', $product->id)->exists())
                        <form method="POST" action="{{ route('favorites.destroy', $product) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">Remove Favorite</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('favorites.store') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="btn btn-outline-secondary">Add to Favorites</button>
                        </form>
                    @endif
                @endauth
            </div>
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
                    <a href="{{ url('/products/'.$relatedProduct->path()) }}" class="card h-100 text-decoration-none">
                        <x-product-thumbnail :path="optional($relatedProduct->primaryImage())->path" :alt="$relatedProduct->name" class="card-img-top" />
                        <div class="card-body">
                            <h6 class="card-title text-dark">{{ $relatedProduct->name }}</h6>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @include('partials.quote-request-form', ['product' => $product])
@endsection

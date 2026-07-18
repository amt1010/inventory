@extends('layouts.app')

@section('title', $category->name ?? 'Products')

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
        </ol>
    </nav>

    @if ($category)
        <h1>{{ $category->name }}</h1>
        @if ($category->description)
            <div class="mb-4">{!! $category->description !!}</div>
        @endif
    @else
        <h1>Products</h1>
    @endif

    @if ($children->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-3 g-4">
            @foreach ($children as $child)
                <div class="col">
                    <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($child->slug)->implode('/')) }}" class="card h-100 text-decoration-none">
                        @if ($child->image)
                            <img src="{{ asset('storage/'.$child->image) }}" class="card-img-top" alt="{{ $child->name }}">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title text-dark">{{ $child->name }}</h5>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($products->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-3 g-4 mt-2">
            @foreach ($products as $product)
                <div class="col">
                    <a href="{{ url('/products/'.collect($breadcrumb)->pluck('slug')->push($product->slug)->implode('/')) }}" class="card h-100 text-decoration-none">
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
    @endif
@endsection

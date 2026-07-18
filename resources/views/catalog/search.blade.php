@extends('layouts.app')

@section('title', 'Search Results')

@section('content')
    <h1>Search Results for "{{ $query }}"</h1>

    @if ($categories->isNotEmpty())
        <h2 class="h4 mt-3">Categories</h2>
        <div class="list-group mb-4">
            @foreach ($categories as $category)
                <a href="{{ url('/products/'.$category->path()) }}" class="list-group-item list-group-item-action">
                    {{ $category->name }}
                    @if ($category->parent)
                        <small class="text-muted">in {{ $category->parent->name }}</small>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    @if ($categories->isNotEmpty())
        <h2 class="h4">Products</h2>
    @endif

    @if ($results->isEmpty() && $categories->isEmpty())
        <p>No results found.</p>
    @elseif ($results->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-3 g-4">
            @foreach ($results as $product)
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
    @endif
@endsection

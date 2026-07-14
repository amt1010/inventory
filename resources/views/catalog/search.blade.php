@extends('layouts.app')

@section('title', 'Search Results')

@section('content')
    <h1>Search Results for "{{ $query }}"</h1>

    @if ($results->isEmpty())
        <p>No products found.</p>
    @else
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

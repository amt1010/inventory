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
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ $product->name }}</h5>
                            <p class="card-text text-muted">{{ $product->short_description }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

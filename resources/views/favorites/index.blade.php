{{-- resources/views/favorites/index.blade.php --}}
@extends('layouts.app')

@section('title', 'My Favorites')

@section('content')
    <h1>My Favorites</h1>

    @if ($favorites->isEmpty())
        <p class="text-muted">You haven't favorited any products yet.</p>
    @else
        <div class="row row-cols-1 row-cols-md-3 g-4">
            @foreach ($favorites as $favorite)
                <div class="col">
                    <div class="card h-100">
                        <a href="{{ url('/products/'.$favorite->product->path()) }}" class="text-decoration-none">
                            @if ($favorite->product->images->first())
                                <img src="{{ asset('storage/'.$favorite->product->images->first()->path) }}" class="card-img-top" alt="{{ $favorite->product->name }}">
                            @endif
                            <div class="card-body">
                                <h5 class="card-title text-dark">{{ $favorite->product->name }}</h5>
                            </div>
                        </a>
                        <div class="card-footer">
                            <form method="POST" action="{{ route('favorites.destroy', $favorite->product) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

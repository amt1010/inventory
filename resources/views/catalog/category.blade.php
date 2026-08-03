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
        <div class="row row-cols-1 row-cols-md-3 g-4 mt-2" id="product-grid">
            @include('catalog.partials.product-grid-items', ['products' => $products, 'breadcrumb' => $breadcrumb])
        </div>
        <div class="text-center py-4" id="product-grid-loader" style="display: none;">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="product-grid-sentinel" data-next-page-url="{{ $products instanceof \Illuminate\Contracts\Pagination\Paginator ? $products->nextPageUrl() : '' }}"></div>

        <script>
            (function () {
                var sentinel = document.getElementById('product-grid-sentinel');
                var grid = document.getElementById('product-grid');
                var loader = document.getElementById('product-grid-loader');
                var loading = false;

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            loadNextPage();
                        }
                    });
                });

                function loadNextPage() {
                    var nextUrl = sentinel.getAttribute('data-next-page-url');
                    if (!nextUrl || loading) {
                        return;
                    }

                    loading = true;
                    loader.style.display = 'block';

                    fetch(nextUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (response) {
                            var nextPageUrl = response.headers.get('X-Next-Page-Url') || '';
                            return response.text().then(function (html) {
                                return { html: html, nextPageUrl: nextPageUrl };
                            });
                        })
                        .then(function (result) {
                            grid.insertAdjacentHTML('beforeend', result.html);
                            sentinel.setAttribute('data-next-page-url', result.nextPageUrl);
                            loader.style.display = 'none';
                            loading = false;

                            if (!result.nextPageUrl) {
                                observer.disconnect();
                            }
                        })
                        .catch(function () {
                            loader.style.display = 'none';
                            loading = false;
                        });
                }

                if (sentinel.getAttribute('data-next-page-url')) {
                    observer.observe(sentinel);
                }
            })();
        </script>
    @endif
@endsection

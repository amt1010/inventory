@extends('layouts.app')

@section('title', $category->name ?? 'Products')

@section('content')
    @if ($preview ?? false)
        <div class="alert alert-warning">Staff preview — this page may not be publicly visible yet.</div>
    @endif
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            @include('catalog.partials.breadcrumb', ['breadcrumb' => $breadcrumb])
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

    @if ($products->isNotEmpty() || $filterGroups->isNotEmpty())
        <form id="catalog-filter-form" method="GET"></form>

        <div class="row">
            <div class="col-md-3 mb-4">
                @if ($filterGroups->isNotEmpty())
                    <div class="md-card p-3">
                        <h6 class="mb-3">Filters</h6>
                        @php $selectedAttrs = (array) request('attr', []); @endphp
                        @foreach ($filterGroups as $label => $values)
                            @php $selectedValues = (array) ($selectedAttrs[$label] ?? []); @endphp
                            <div class="mb-3">
                                <div class="fw-bold small text-uppercase mb-2">{{ $label }}</div>
                                @foreach ($values as $value)
                                    @php $optionId = 'attr-'.\Illuminate\Support\Str::slug($label).'-'.\Illuminate\Support\Str::slug($value); @endphp
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            form="catalog-filter-form"
                                            name="attr[{{ $label }}][]"
                                            value="{{ $value }}"
                                            id="{{ $optionId }}"
                                            onchange="document.getElementById('catalog-filter-form').submit()"
                                            @checked(in_array($value, $selectedValues))
                                        >
                                        <label class="form-check-label" for="{{ $optionId }}">{{ $value }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-md-9">
                @if ($products->isNotEmpty())
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">{{ $products->total() }} products found</span>
                        <div>
                            <label for="sort" class="small text-muted me-2">Sort by</label>
                            <select
                                name="sort"
                                id="sort"
                                form="catalog-filter-form"
                                class="form-select d-inline-block w-auto"
                                onchange="document.getElementById('catalog-filter-form').submit()"
                            >
                                <option value="">Featured</option>
                                <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                                <option value="name_asc" @selected(request('sort') === 'name_asc')>Name (A-Z)</option>
                                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name (Z-A)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row row-cols-1 row-cols-md-3 g-4" id="product-grid">
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
            </div>
        </div>
    @endif
@endsection

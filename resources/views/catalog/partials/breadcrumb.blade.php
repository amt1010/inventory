{{-- resources/views/catalog/partials/breadcrumb.blade.php --}}
{{--
    $breadcrumb is always the FULL ancestor chain (root -> leaf), regardless
    of each category's own show_in_breadcrumb flag -- a hidden category's
    slug must still contribute to a deeper, visible category's URL, it just
    doesn't get its own rendered <li>.
--}}
<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
<li class="breadcrumb-item"><a href="{{ url('/products') }}">Products</a></li>
@php
    $slugs = collect($breadcrumb)->pluck('slug');
@endphp
@foreach ($breadcrumb as $index => $crumb)
    @if ($crumb->show_in_breadcrumb)
        <li class="breadcrumb-item">
            <a href="{{ url('/products/'.$slugs->take($index + 1)->implode('/')) }}">{{ $crumb->name }}</a>
        </li>
    @endif
@endforeach

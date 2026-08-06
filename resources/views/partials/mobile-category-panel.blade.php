{{-- resources/views/partials/mobile-category-panel.blade.php --}}
@php
    $panelId = $panelId ?? 'root';
    $heading = $heading ?? 'Products';
@endphp
<div class="mcn-panel" data-mcn-panel="{{ $panelId }}">
    <button type="button" class="mcn-back d-flex align-items-center gap-2" data-mcn-back>
        <span aria-hidden="true">&larr;</span> {{ $heading }}
    </button>
    <ul class="list-unstyled mb-0">
        @foreach ($nodes as $node)
            <li class="d-flex align-items-center justify-content-between">
                <a href="{{ url('/products/'.$node['path']) }}" class="mcn-row-link flex-grow-1">{{ $node['name'] }}</a>
                @if (!empty($node['children']))
                    <button type="button" class="mcn-drill-in" data-mcn-open="cat-{{ $node['id'] }}" aria-label="Browse {{ $node['name'] }} subcategories">&rsaquo;</button>
                @endif
            </li>
        @endforeach
    </ul>
</div>

@foreach ($nodes as $node)
    @if (!empty($node['children']))
        @include('partials.mobile-category-panel', [
            'nodes' => $node['children'],
            'panelId' => 'cat-'.$node['id'],
            'heading' => $node['name'],
        ])
    @endif
@endforeach

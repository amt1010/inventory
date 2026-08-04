{{-- resources/views/blocks/hero_banner.blade.php --}}
<div class="md-hero mb-4 py-5">
    <div class="row align-items-center g-5">
        <div class="col-md-7">
            @if (!empty($data['tag']))
                <span class="md-tag md-tag-accent mb-3 d-inline-block">{{ $data['tag'] }}</span>
            @endif
            <h1>{{ $data['heading'] }}</h1>
            @if (!empty($data['body']))
                <p class="lead">{{ $data['body'] }}</p>
            @endif
            @if (!empty($data['search_placeholder']))
                <form class="d-flex mt-4" style="max-width: 480px;" action="{{ route('catalog.search') }}" method="GET">
                    <input class="form-control me-2" type="search" name="q" placeholder="{{ $data['search_placeholder'] }}">
                    <button class="md-btn md-btn-primary" type="submit">Search</button>
                </form>
            @endif
            <div class="d-flex gap-3 mt-4">
                @if (!empty($data['cta_primary_label']) && !empty($data['cta_primary_url']))
                    <a href="{{ $data['cta_primary_url'] }}" class="md-btn md-btn-primary">{{ $data['cta_primary_label'] }}</a>
                @endif
                @if (!empty($data['cta_secondary_label']) && !empty($data['cta_secondary_url']))
                    <a href="{{ $data['cta_secondary_url'] }}" class="md-btn md-btn-secondary">{{ $data['cta_secondary_label'] }}</a>
                @endif
            </div>
        </div>
        @if (!empty($data['image']))
            <div class="col-md-5">
                <img src="{{ asset('storage/'.$data['image']) }}" class="img-fluid md-grayscale" alt="{{ $data['heading'] ?? '' }}">
            </div>
        @endif
    </div>
</div>

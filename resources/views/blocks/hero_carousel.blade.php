{{-- resources/views/blocks/hero_carousel.blade.php --}}
@php
    $slides = collect($data['slides'] ?? [])->filter(fn ($slide) => $slide['active'] ?? true)->values();
    $carouselId = 'heroCarousel'.($blockKey ?? 0);
@endphp
@if ($slides->isNotEmpty())
    <div id="{{ $carouselId }}" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-indicators">
            @foreach ($slides as $index => $slide)
                <button type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide-to="{{ $index }}"
                    @if ($index === 0) class="active" aria-current="true" @endif
                    aria-label="Slide {{ $index + 1 }}"></button>
            @endforeach
        </div>
        <div class="carousel-inner rounded-3">
            @foreach ($slides as $index => $slide)
                <div class="carousel-item @if ($index === 0) active @endif">
                    @if (($slide['media_type'] ?? 'image') === 'video' && !empty($slide['video_url']))
                        <video class="d-block w-100" style="max-height: 480px; object-fit: cover;" autoplay muted loop playsinline>
                            <source src="{{ $slide['video_url'] }}">
                        </video>
                    @elseif (!empty($slide['image']))
                        <img src="{{ asset('storage/'.$slide['image']) }}" class="d-block w-100" style="max-height: 480px; object-fit: cover;" alt="{{ $slide['heading'] ?? '' }}">
                    @endif
                    <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded-3 p-3">
                        @if (!empty($slide['heading']))
                            <h2>{{ $slide['heading'] }}</h2>
                        @endif
                        @if (!empty($slide['subheading']))
                            <p>{{ $slide['subheading'] }}</p>
                        @endif
                        @if (!empty($slide['cta_label']) && !empty($slide['cta_url']))
                            <a href="{{ $slide['cta_url'] }}" class="btn btn-primary">{{ $slide['cta_label'] }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @if ($slides->count() > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        @endif
    </div>
@endif

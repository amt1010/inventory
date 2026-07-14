{{-- resources/views/blocks/content_strip.blade.php --}}
@php
    $imageFirst = ($data['image_position'] ?? 'left') === 'left';
@endphp
<div class="row align-items-center g-4 mb-4">
    @if ($imageFirst && !empty($data['image']))
        <div class="col-md-6">
            <img src="{{ asset('storage/'.$data['image']) }}" class="img-fluid rounded-3" alt="{{ $data['heading'] ?? '' }}">
        </div>
    @endif
    <div class="col-md-6">
        @if (!empty($data['heading']))
            <h2>{{ $data['heading'] }}</h2>
        @endif
        @if (!empty($data['body']))
            <div>{!! $data['body'] !!}</div>
        @endif
    </div>
    @if (! $imageFirst && !empty($data['image']))
        <div class="col-md-6">
            <img src="{{ asset('storage/'.$data['image']) }}" class="img-fluid rounded-3" alt="{{ $data['heading'] ?? '' }}">
        </div>
    @endif
</div>

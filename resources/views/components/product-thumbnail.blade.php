@props(['path' => null, 'alt' => ''])

@if ($path)
    <img
        src="{{ asset('storage/'.$path) }}"
        alt="{{ $alt }}"
        width="132"
        height="132"
        style="width:132px;height:132px;object-fit:cover;"
        {{ $attributes->merge(['class' => 'rounded']) }}
    >
@endif

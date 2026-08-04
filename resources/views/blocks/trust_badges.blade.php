{{-- resources/views/blocks/trust_badges.blade.php --}}
@php
    $icons = [
        'shield-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>',
        'package-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 16 2 2 4-4"/><path d="M21 12.5V6.5a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 6.5v9a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l1.5-.86"/><path d="M3.29 7 12 12l8.71-5"/><path d="M12 22V12"/></svg>',
        'handshake' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 4h8"/></svg>',
        'message-square' => '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    ];
@endphp
<div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
    @foreach ($data['items'] ?? [] as $badge)
        <div class="col d-flex align-items-start gap-3">
            @if (isset($icons[$badge['icon'] ?? '']))
                <span class="text-danger flex-shrink-0">{!! $icons[$badge['icon']] !!}</span>
            @endif
            <span class="fw-bold">{{ $badge['label'] ?? '' }}</span>
        </div>
    @endforeach
</div>

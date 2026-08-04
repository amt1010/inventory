{{-- resources/views/blocks/deals_banner.blade.php --}}
<div class="md-card mb-4 p-5 text-center" style="background: var(--color-accent); color: #fff; border-color: var(--color-accent);">
    @if (!empty($data['heading']))
        <h2 style="color: #fff;">{{ $data['heading'] }}</h2>
    @endif
    @if (!empty($data['body']))
        <p class="mb-4">{{ $data['body'] }}</p>
    @endif
    @if (!empty($data['cta_label']) && !empty($data['cta_url']))
        <a href="{{ $data['cta_url'] }}" class="md-btn md-btn-secondary" style="color: #fff; border-color: #fff;">{{ $data['cta_label'] }}</a>
    @endif
</div>

{{-- resources/views/blocks/rfq_form_embed.blade.php --}}
<div class="md-rfq-card md-card p-4 p-md-5 mb-4">
    @if (!empty($data['tag']))
        <span class="md-tag md-tag-accent mb-3 d-inline-block">{{ $data['tag'] }}</span>
    @endif
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    @if (!empty($data['body']))
        <p class="text-muted">{{ $data['body'] }}</p>
    @endif
    @include('partials.quote-request-form-fields', ['product' => null, 'modal' => false, 'idSuffix' => '-embed-'.($blockKey ?? 0)])
</div>

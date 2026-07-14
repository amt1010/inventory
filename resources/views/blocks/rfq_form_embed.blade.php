{{-- resources/views/blocks/rfq_form_embed.blade.php --}}
<div class="mb-4">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    @include('partials.quote-request-form-fields', ['product' => null, 'modal' => false])
</div>

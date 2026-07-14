{{-- resources/views/partials/quote-request-form.blade.php --}}
@php
    $modalId = isset($product) ? 'quoteRequestModal-'.$product->id : 'quoteRequestModal';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request a Quote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('partials.quote-request-form-fields', ['product' => $product ?? null, 'modal' => true])
            </div>
        </div>
    </div>
</div>

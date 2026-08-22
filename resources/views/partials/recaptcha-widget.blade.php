{{-- resources/views/partials/recaptcha-widget.blade.php --}}
@if (config('services.recaptcha.site_key'))
    <div class="mb-3">
        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
    </div>
@endif

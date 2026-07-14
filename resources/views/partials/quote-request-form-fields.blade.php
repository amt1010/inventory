{{-- resources/views/partials/quote-request-form-fields.blade.php --}}
@php
    $defaultReason = isset($product) ? 'Request a Quote' : 'General Inquiry';
    $idSuffix = $idSuffix ?? (isset($product) ? '-'.$product->id : '');
@endphp

<form action="{{ route('quote-requests.store') }}" method="POST">
    @csrf
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @isset($product)
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <p class="text-muted">Regarding: <strong>{{ $product->name }}</strong></p>
    @endisset
    <input type="hidden" name="source_url" value="{{ url()->current() }}">

    <div class="mb-3">
        <label class="form-label">Reason for Contact</label>
        <select name="reason" class="form-select" required>
            @foreach (config('rfq.reasons') as $value => $label)
                <option value="{{ $value }}" @selected($value === $defaultReason)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Company</label>
            <input type="text" name="company" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Select Country</label>
            <select name="country" class="form-select">
                <option value="">Select Country</option>
                @foreach (config('rfq.countries') as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Select Market</label>
            <select name="market" class="form-select">
                <option value="">Select Market</option>
                @foreach (config('rfq.markets') as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" rows="4">{{ isset($product) ? 'I am interested in '.$product->name.' ('.url()->current().')' : '' }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label d-block">How would you prefer to be contacted?</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="contact_preference" value="email" id="contact-email{{ $idSuffix }}" checked>
            <label class="form-check-label" for="contact-email{{ $idSuffix }}">Email</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="contact_preference" value="phone" id="contact-phone{{ $idSuffix }}">
            <label class="form-check-label" for="contact-phone{{ $idSuffix }}">Phone</label>
        </div>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="privacy_policy" class="form-check-input" id="privacy{{ $idSuffix }}" required>
        <label class="form-check-label" for="privacy{{ $idSuffix }}">I have read and accepted the Privacy Policy.</label>
    </div>

    @if (config('services.recaptcha.site_key'))
        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
    @endif

    <div class="d-flex justify-content-end gap-2 mt-2">
        @if ($modal ?? false)
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        @endif
        <button type="submit" class="btn btn-primary">Submit</button>
    </div>
</form>

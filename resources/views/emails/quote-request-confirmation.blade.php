<h1>Thank you, {{ $quoteRequest->first_name }}!</h1>

<p>We've received your quote request. Your reference number is:</p>

<p style="font-size: 1.5em; font-weight: bold;">{{ $quoteRequest->quote_number }}</p>

<p>Please quote this number in any follow-up correspondence about this enquiry.</p>

@if ($quoteRequest->product)
    <p><strong>Product:</strong> {{ $quoteRequest->product->name }}</p>
@endif

<p>Our team will be in touch shortly.</p>

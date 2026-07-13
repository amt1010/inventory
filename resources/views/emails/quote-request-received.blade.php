<h1>New Quote Request</h1>

<p><strong>Reason:</strong> {{ $quoteRequest->reason }}</p>
<p><strong>Name:</strong> {{ $quoteRequest->fullName() }}</p>
<p><strong>Email:</strong> {{ $quoteRequest->email }}</p>
<p><strong>Phone:</strong> {{ $quoteRequest->phone }}</p>
<p><strong>Company:</strong> {{ $quoteRequest->company }}</p>

@if ($quoteRequest->product)
    <p><strong>Product:</strong> {{ $quoteRequest->product->name }}</p>
@endif

@if ($quoteRequest->message)
    <p><strong>Message:</strong></p>
    <p>{{ $quoteRequest->message }}</p>
@endif

<p>
    <a href="{{ url('/admin/quote-requests/'.$quoteRequest->id) }}">View in the CMS</a>
</p>

<h1>Update on your application</h1>
<p>Thank you for applying to become a seller. Unfortunately, we're unable to approve {{ $seller->company_name }}'s application at this time.</p>

@if ($seller->rejection_reason)
    <p><strong>Reason:</strong> {{ $seller->rejection_reason }}</p>
@endif

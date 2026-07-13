<h1>Activate your seller account</h1>

@if ($seller->created_by === 'admin')
    <p>An administrator has created a seller account for {{ $seller->company_name }}. Click below to set your password and activate your account.</p>
@else
    <p>Thanks for registering {{ $seller->company_name }}. Click below to verify your email address.</p>
@endif

<p><a href="{{ $activationUrl }}">Activate Account</a></p>

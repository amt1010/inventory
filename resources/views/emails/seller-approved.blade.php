<h1>You're approved!</h1>
<p>Congratulations — {{ $seller->company_name }}'s seller account has been approved. You can now log in and start listing products.</p>

@if ($activationUrl)
    <p>Before you can log in, set your password: <a href="{{ $activationUrl }}">Set Your Password</a></p>
@endif

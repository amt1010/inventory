<h1>Welcome to the admin panel</h1>
<p>An account has been created for you, {{ $staff->name }}.</p>
<p>Log in at <a href="{{ $loginUrl }}">{{ $loginUrl }}</a> using this temporary password:</p>
<p><strong>{{ $temporaryPassword }}</strong></p>
<p>You'll be asked to set a new password the first time you log in.</p>

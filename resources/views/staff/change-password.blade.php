<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Set a new password</title>
</head>
<body>
    <h1>Set a new password</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.change-password.update') }}">
        @csrf
        <label for="password">New password</label>
        <input type="password" name="password" id="password" required>

        <label for="password_confirmation">Confirm new password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" required>

        <button type="submit">Set password</button>
    </form>
</body>
</html>

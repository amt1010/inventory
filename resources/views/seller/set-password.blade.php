@extends('layouts.app')

@section('title', 'Set Your Password')

@section('content')
    <h1>Set Your Password</h1>
    <p>An administrator has created a seller account for <strong>{{ $seller->company_name }}</strong>. Set a password to activate it.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ url()->full() }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Activate Account</button>
    </form>
@endsection

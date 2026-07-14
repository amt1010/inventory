{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.app')

@section('title', 'Log In')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1>Log In</h1>

            <form method="POST" action="{{ route('login.store') }}">
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

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Log In</button>
                <a href="{{ route('register') }}" class="btn btn-link">Create an account</a>
            </form>
        </div>
    </div>
@endsection

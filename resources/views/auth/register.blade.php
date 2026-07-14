{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.app')

@section('title', 'Create an Account')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1>Create an Account</h1>
            <p class="text-muted">Optional — track your past quote requests and save favorites.</p>

            <form method="POST" action="{{ route('register.store') }}">
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
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Create Account</button>
                <a href="{{ route('login') }}" class="btn btn-link">Already have an account?</a>
            </form>
        </div>
    </div>
@endsection

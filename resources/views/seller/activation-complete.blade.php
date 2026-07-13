@extends('layouts.app')

@section('title', 'Account Activated')

@section('content')
    @if ($seller->status === 'approved')
        <h1>Your account is ready</h1>
        <p>Your password has been set and your account is active. You can now log in to the seller portal.</p>
    @else
        <h1>Email verified</h1>
        <p>Thanks — your email address has been verified. Our team will review your application and get back to you shortly.</p>
    @endif
@endsection

@extends('layouts.app')

@section('title', 'Sell With Us')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1>Sell With Us</h1>
            <p class="lead text-muted">
                List your products on our platform and reach buyers actively sourcing cable, wire, and
                related inventory. Our team reviews and prices every listing before it goes live.
            </p>

            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="{{ route('seller.register') }}" class="btn btn-primary btn-lg">Register as a Seller</a>
                <a href="{{ route('filament.seller.auth.login') }}" class="btn btn-outline-secondary btn-lg">Already a seller? Log In</a>
            </div>
        </div>
    </div>
@endsection

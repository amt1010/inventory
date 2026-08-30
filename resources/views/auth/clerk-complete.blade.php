{{-- resources/views/auth/clerk-complete.blade.php --}}
@extends('layouts.app')

@section('title', 'Signing you in')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <p id="clerk-status">Signing you in&hellip;</p>
        </div>
    </div>

    <script>
        window.addEventListener('load', async function () {
            const statusEl = document.getElementById('clerk-status');

            await window.Clerk.load();

            if (!window.Clerk.session) {
                statusEl.textContent = 'Sign-in did not complete. Please try again.';
                return;
            }

            const endpoints = {
                buyer: @json(route('auth.clerk.buyer')),
                seller_register: @json(route('seller.clerk.register')),
                seller_login: @json(route('seller.clerk.login')),
            };

            const intent = new URLSearchParams(window.location.search).get('intent');
            const endpoint = endpoints[intent];

            if (!endpoint) {
                statusEl.textContent = 'Something went wrong. Please try again.';
                return;
            }

            const token = await window.Clerk.session.getToken();

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ token: token }),
            });

            const data = await response.json();

            if (response.ok) {
                window.location.href = data.redirect;
            } else {
                statusEl.textContent = data.error || 'Something went wrong. Please try again.';
            }
        });
    </script>
@endsection

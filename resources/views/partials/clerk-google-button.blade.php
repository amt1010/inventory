{{-- resources/views/partials/clerk-google-button.blade.php --}}
@if (config('services.clerk.publishable_key'))
    <div class="d-grid mb-3">
        <button type="button" id="clerk-google-btn-{{ $intent }}" class="btn btn-outline-dark">
            Continue with Google
        </button>
    </div>
    <div class="text-center text-muted mb-3">or</div>

    <script>
        window.addEventListener('load', async function () {
            await window.Clerk.load();

            document.getElementById('clerk-google-btn-{{ $intent }}').addEventListener('click', async function () {
                await window.Clerk.client.signIn.authenticateWithRedirect({
                    strategy: 'oauth_google',
                    redirectUrl: '{{ route('auth.clerk.complete') }}?intent={{ $intent }}',
                    redirectUrlComplete: '{{ route('auth.clerk.complete') }}?intent={{ $intent }}',
                });
            });
        });
    </script>
@endif

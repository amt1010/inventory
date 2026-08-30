{{-- resources/views/filament/partials/clerk-login-button.blade.php --}}
@if (config('services.clerk.publishable_key'))
    <div class="mb-4">
        <button
            type="button"
            id="clerk-google-seller-login"
            class="fi-btn fi-btn-color-gray fi-btn-size-md flex w-full items-center justify-center gap-1 rounded-lg border px-3 py-2 text-sm font-semibold"
        >
            Continue with Google
        </button>
        <p class="mt-2 text-center text-xs text-gray-500">or sign in with your password below</p>
    </div>

    <script
        async
        crossorigin="anonymous"
        data-clerk-publishable-key="{{ config('services.clerk.publishable_key') }}"
        src="https://{{ config('services.clerk.frontend_api') }}/npm/@clerk/clerk-js@latest/dist/clerk.browser.js"
        type="text/javascript"
    ></script>
    <script>
        window.addEventListener('load', async function () {
            await window.Clerk.load();

            document.getElementById('clerk-google-seller-login').addEventListener('click', async function () {
                await window.Clerk.client.signIn.authenticateWithRedirect({
                    strategy: 'oauth_google',
                    redirectUrl: '{{ route('auth.clerk.complete') }}?intent=seller_login',
                    redirectUrlComplete: '{{ route('auth.clerk.complete') }}?intent=seller_login',
                });
            });
        });
    </script>
@endif

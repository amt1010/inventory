{{-- resources/views/partials/cookie-consent-banner.blade.php --}}
<div id="cookie-consent-banner" class="cookie-consent-banner" style="display: none;" role="dialog" aria-live="polite" aria-label="Cookie notice">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
        <p class="mb-0 small">
            We use cookies to keep you signed in and remember your preferences while you browse.
            We don't use cookies for advertising or tracking.
        </p>
        <button type="button" id="cookie-consent-accept" class="btn btn-primary btn-sm">Accept</button>
    </div>
</div>

<script>
    (function () {
        var COOKIE_NAME = 'cookie_consent_ack';
        var banner = document.getElementById('cookie-consent-banner');
        var acceptBtn = document.getElementById('cookie-consent-accept');
        var settingsLink = document.getElementById('cookie-settings-link');

        function hasConsentCookie() {
            return document.cookie.split('; ').some(function (row) {
                return row.indexOf(COOKIE_NAME + '=') === 0;
            });
        }

        function showBanner() {
            banner.style.display = 'block';
        }

        function hideBanner() {
            banner.style.display = 'none';
        }

        function acceptConsent() {
            // No Max-Age/Expires: a session cookie, cleared when the browser closes,
            // matching "displayed one time during the session".
            document.cookie = COOKIE_NAME + '=1; path=/; SameSite=Lax';
            hideBanner();
        }

        if (!hasConsentCookie()) {
            showBanner();
        }

        acceptBtn.addEventListener('click', acceptConsent);

        if (settingsLink) {
            settingsLink.addEventListener('click', function (event) {
                event.preventDefault();
                showBanner();
            });
        }
    })();
</script>

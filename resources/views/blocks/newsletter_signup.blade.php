{{-- resources/views/blocks/newsletter_signup.blade.php --}}
<div class="md-card p-4 p-md-5 mb-4 text-center">
    @if (!empty($data['heading']))
        <h2>{{ $data['heading'] }}</h2>
    @endif
    @if (!empty($data['subheading']))
        <p class="text-muted">{{ $data['subheading'] }}</p>
    @endif
    <form class="mt-3" style="max-width: 480px; margin: 0 auto;" action="{{ route('newsletter.subscribe') }}" method="POST">
        @csrf
        <div class="d-flex justify-content-center gap-2">
            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            <button type="submit" class="md-btn md-btn-primary">Subscribe</button>
        </div>
        <div class="d-flex justify-content-center mt-2">
            @include('partials.recaptcha-widget')
        </div>
    </form>
</div>

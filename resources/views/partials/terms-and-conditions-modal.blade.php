{{-- resources/views/partials/terms-and-conditions-modal.blade.php --}}
@php
    $idSuffix = $idSuffix ?? '';
@endphp

<div class="mb-3 form-check">
    <input type="checkbox" name="terms_accepted" class="form-check-input" id="terms-accepted-checkbox{{ $idSuffix }}" value="1" required disabled>
    <label class="form-check-label" for="terms-accepted-checkbox{{ $idSuffix }}">
        I have read and accept the
        @if ($termsPage)
            <a href="#" data-bs-toggle="modal" data-bs-target="#terms-modal{{ $idSuffix }}">Terms &amp; Conditions</a>
        @else
            Terms &amp; Conditions
        @endif
    </label>
    @error('terms_accepted')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

@if ($termsPage)
    <div class="modal fade" id="terms-modal{{ $idSuffix }}" tabindex="-1" aria-labelledby="terms-modal-label{{ $idSuffix }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="terms-modal-label{{ $idSuffix }}">{{ $termsPage->title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    @foreach ($termsPage->content as $block)
                        @if (! empty($block['data']['heading']))
                            <h5>{{ $block['data']['heading'] }}</h5>
                        @endif
                        @if (! empty($block['data']['body']))
                            <div>{!! $block['data']['body'] !!}</div>
                        @endif
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="terms-decline-btn{{ $idSuffix }}" data-bs-dismiss="modal">Decline</button>
                    <button type="button" class="btn btn-primary" id="terms-accept-btn{{ $idSuffix }}" data-bs-dismiss="modal">Accept</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var checkbox = document.getElementById('terms-accepted-checkbox{{ $idSuffix }}');
            var acceptBtn = document.getElementById('terms-accept-btn{{ $idSuffix }}');
            var declineBtn = document.getElementById('terms-decline-btn{{ $idSuffix }}');
            var submitBtn = checkbox.closest('form').querySelector('button[type="submit"]');

            function syncSubmitState() {
                submitBtn.disabled = !checkbox.checked;
            }

            checkbox.disabled = false;
            checkbox.addEventListener('change', syncSubmitState);
            acceptBtn.addEventListener('click', function () {
                checkbox.checked = true;
                syncSubmitState();
            });
            declineBtn.addEventListener('click', function () {
                checkbox.checked = false;
                syncSubmitState();
            });

            syncSubmitState();
        })();
    </script>
@else
    <script>
        document.getElementById('terms-accepted-checkbox{{ $idSuffix }}').disabled = false;
    </script>
@endif

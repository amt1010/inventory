{{-- resources/views/filament/partials/brand.blade.php --}}
{{--
    Filament's own logo component only ever renders ONE of image-or-text
    (see vendor/filament/filament/resources/views/components/logo.blade.php) --
    passing this view as the brandLogo() closure is the supported way to show
    both together, matching the public site's header (layouts/app.blade.php).
--}}
<div class="flex items-center gap-2">
    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" style="height: 1.5rem">
    <span class="text-sm font-bold leading-5 tracking-tight text-gray-950 dark:text-white">{{ $brandName }}</span>
</div>

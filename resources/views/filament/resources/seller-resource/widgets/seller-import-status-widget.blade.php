<div wire:poll.5s>
    @php $import = $this->getImport(); @endphp

    @if ($import)
        <x-filament-widgets::widget>
            <x-filament::section>
                @if ($this->isStuck($import))
                    <div class="flex items-center gap-x-3">
                        <x-filament::badge color="danger">Stuck</x-filament::badge>
                        <p class="text-sm text-gray-950 dark:text-white">
                            This import hasn't made progress in over
                            {{ config('imports.stuck_after_minutes') }} minutes.
                            The queue worker may be offline.
                        </p>
                    </div>
                @else
                    <p class="mb-2 text-sm text-gray-950 dark:text-white">
                        Importing sellers: {{ $import->processed_rows }} of {{ $import->total_rows }} rows
                    </p>
                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-2 rounded-full bg-primary-600"
                            style="width: {{ $import->total_rows > 0 ? min(100, intdiv($import->processed_rows * 100, $import->total_rows)) : 0 }}%"
                        ></div>
                    </div>
                @endif
            </x-filament::section>
        </x-filament-widgets::widget>
    @endif
</div>

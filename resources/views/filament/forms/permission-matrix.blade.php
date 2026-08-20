@php
    $selected = collect($getState() ?? [])
        ->filter(fn ($permission) => is_string($permission))
        ->values()
        ->all();
@endphp

<div
    class="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700"
    x-data="{
        values: @js(collect($selected)->mapWithKeys(fn ($permission) => [str($permission)->before('.')->value() => str($permission)->after('.')->value()])->all()),
        sync() {
            $wire.set(@js($getStatePath()), Object.entries(this.values)
                .filter(([, tier]) => tier)
                .map(([area, tier]) => `${area}.${tier}`));
        },
    }"
>
    <table class="w-full min-w-[620px] border-collapse text-sm">
        <thead>
            <tr class="bg-gray-50 dark:bg-gray-800">
                <th class="border border-gray-300 px-3 py-2 text-left font-semibold dark:border-gray-700">Area</th>
                @foreach (\App\Filament\Resources\RoleResource::TIERS as $tier => $label)
                    <th class="border border-gray-300 px-3 py-2 text-center font-semibold dark:border-gray-700">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Filament\Resources\RoleResource::AREAS as $area => $label)
                <tr>
                    <th class="border border-gray-300 px-3 py-2 text-left font-medium dark:border-gray-700">{{ $label }}</th>
                    @foreach (\App\Filament\Resources\RoleResource::TIERS as $tier => $tierLabel)
                        <td class="border border-gray-300 px-3 py-2 text-center dark:border-gray-700">
                            <input
                                type="radio"
                                name="permission_{{ $area }}"
                                value="{{ $tier }}"
                                x-model="values['{{ $area }}']"
                                x-on:change="sync()"
                                aria-label="{{ $label }} {{ $tierLabel }}"
                                class="h-4 w-4 border-gray-400 text-primary-600 focus:ring-primary-600"
                            >
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

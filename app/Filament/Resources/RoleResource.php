<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 8;

    public const AREAS = [
        'dashboard' => 'Dashboard',
        'staff' => 'Staff',
        'categories' => 'Categories',
        'products' => 'Products',
        'sellers' => 'Sellers',
        'quote_requests' => 'Quote Requests',
        'pages' => 'Pages',
        'nav_items' => 'Nav Items',
        'settings' => 'Settings',
    ];

    public const TIERS = ['read' => 'Read', 'write' => 'Write', 'full' => 'Full'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->rule(fn ($record) => Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'staff'))
                    ->ignore($record?->id)),
            CheckboxList::make('permissions')
                ->label('Permissions')
                ->options(self::permissionOptions())
                ->columns(3)
                ->bulkToggleable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('access')
                    ->label('Access')
                    ->state(fn (Role $record) => self::summarize($record)),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function permissionsFromFormData(array $data): array
    {
        if (! empty($data['permissions']) || ! array_key_exists('tier_categories', $data)) {
            return array_values(array_filter($data['permissions'] ?? []));
        }

        $permissions = [];

        foreach (array_keys(self::AREAS) as $area) {
            $tier = $data["tier_{$area}"] ?? 'none';

            if ($tier !== 'none') {
                $permissions[] = "{$area}.{$tier}";
            }
        }

        return $permissions;
    }

    public static function tiersFromRecord(Role $record): array
    {
        $names = $record->permissions->pluck('name');
        $tiers = [];

        foreach (array_keys(self::AREAS) as $area) {
            $tiers["tier_{$area}"] = 'none';

            foreach (['full', 'write', 'read'] as $tier) {
                if ($names->contains("{$area}.{$tier}")) {
                    $tiers["tier_{$area}"] = $tier;
                    break;
                }
            }
        }

        return $tiers;
    }

    public static function permissionOptions(): array
    {
        $options = [];

        foreach (self::AREAS as $area => $label) {
            foreach (self::TIERS as $tier => $tierLabel) {
                $options["{$area}.{$tier}"] = "{$label}: {$tierLabel}";
            }
        }

        return $options;
    }

    public static function summarize(Role $record): string
    {
        $tiers = self::tiersFromRecord($record);
        $parts = [];

        foreach (self::AREAS as $area => $label) {
            if ($tiers["tier_{$area}"] !== 'none') {
                $parts[] = "{$label}: ".self::TIERS[$tiers["tier_{$area}"]];
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }
}

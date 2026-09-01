<?php

namespace App\Filament\Widgets;

use App\Models\Staff;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffByRoleChart extends ChartWidget
{
    protected static ?string $heading = 'Staff by Role';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth('staff')->user()?->can('viewAny', Staff::class) ?? false;
    }

    /**
     * Queries the pivot table directly rather than Role::withCount('users'):
     * Spatie's Role::users() resolves the guard's model via $this->attributes['guard_name'],
     * which is empty on the bare instance Eloquent uses to build a withCount subquery, so it
     * silently falls back to the default guard's model and counts zero staff.
     *
     * @return array<string, int>
     */
    public function roleCounts(): array
    {
        $roleNames = DB::table('roles')->where('guard_name', 'staff')->orderBy('name')->pluck('name');

        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.guard_name', 'staff')
            ->where('model_has_roles.model_type', Staff::class)
            ->groupBy('roles.name')
            ->selectRaw('roles.name, count(*) as aggregate')
            ->pluck('aggregate', 'name');

        return $roleNames->mapWithKeys(
            fn (string $name) => [Str::headline($name) => (int) ($counts[$name] ?? 0)]
        )->all();
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Staff',
                    'data' => array_values($this->roleCounts()),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_keys($this->roleCounts()),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

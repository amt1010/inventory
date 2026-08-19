<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;

class CategoriesByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Categories by Status';

    protected static bool $isLazy = false;

    private const LABELS = [
        'draft' => 'Draft',
        'published' => 'Published',
    ];

    public static function canView(): bool
    {
        return auth('staff')->user()?->can('viewAny', Category::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = Category::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $result = [];

        foreach (self::LABELS as $status => $label) {
            $result[$label] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                ['label' => 'Categories', 'data' => array_values($this->statusCounts())],
            ],
            'labels' => array_keys($this->statusCounts()),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

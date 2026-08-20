<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\ChartWidget;

class ProductsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Products by Status';

    protected static bool $isLazy = false;

    private const LABELS = [
        'pending_review' => 'Pending Review',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
        'pending_seller_acceptance' => 'Pending Seller Acceptance',
    ];

    public static function canView(): bool
    {
        return auth('staff')->user()?->can('viewAny', Product::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = Product::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

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
                [
                    'label' => 'Products',
                    'data' => array_values($this->statusCounts()),
                    'backgroundColor' => ['#f59e0b', '#10b981', '#ef4444', '#64748b', '#3b82f6'],
                    'borderColor' => ['#d97706', '#059669', '#dc2626', '#475569', '#2563eb'],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_keys($this->statusCounts()),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Support\RawJs;
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
                [
                    'label' => 'Categories',
                    'data' => array_values($this->statusCounts()),
                    'backgroundColor' => ['#f59e0b', '#10b981'],
                    'borderColor' => ['#d97706', '#059669'],
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

    /**
     * Chart.js's default legend shows a single swatch for the whole
     * dataset, which doesn't reflect this chart's per-bar colors. Build
     * one legend entry per bar/status instead, using that bar's own
     * background/border color. The legend already names each bar, so both
     * axes' tick labels are redundant here -- hide them (unlike
     * QuoteRequestsByDateChart, which has no per-bar legend and keeps them).
     */
    protected function getOptions(): array | RawJs | null
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: {
                        ticks: { display: false },
                    },
                    y: {
                        ticks: { display: false },
                    },
                },
                plugins: {
                    legend: {
                        labels: {
                            generateLabels: (chart) => {
                                const data = chart.data;

                                return data.labels.map((label, i) => ({
                                    text: label,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].borderColor[i],
                                    lineWidth: 2,
                                    index: i,
                                }));
                            },
                        },
                    },
                },
            }
        JS);
    }
}

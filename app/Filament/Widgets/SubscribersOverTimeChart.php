<?php

namespace App\Filament\Widgets;

use App\Models\Subscriber;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class SubscribersOverTimeChart extends ChartWidget
{
    private const DAYS = 30;

    protected static ?string $heading = 'Subscribers Over Time';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth('staff')->user()?->can('viewAny', Subscriber::class) ?? false;
    }

    /**
     * Subscriber signups for each of the last {@see self::DAYS} days, oldest
     * first, zero-filled for days with no signups.
     *
     * @return array<string, int>
     */
    public function dailyCounts(): array
    {
        $start = now()->subDays(self::DAYS - 1)->startOfDay();

        $counts = Subscriber::query()
            ->selectRaw('DATE(created_at) as day, count(*) as aggregate')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $result = [];

        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $result[$key] = (int) ($counts[$key] ?? 0);
        }

        return $result;
    }

    protected function getData(): array
    {
        $counts = $this->dailyCounts();

        return [
            'datasets' => [
                [
                    'label' => 'Subscribers',
                    'data' => array_values($counts),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_keys($counts),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array | RawJs | null
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                },
            }
        JS);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\QuoteRequestResource;
use App\Models\QuoteRequest;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class QuoteRequestsByDateChart extends ChartWidget
{
    private const DAYS = 30;

    protected static ?string $heading = 'Quote Requests by Date';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth('staff')->user()?->can('viewAny', QuoteRequest::class) ?? false;
    }

    /**
     * Request counts for each of the last {@see self::DAYS} days, oldest
     * first, zero-filled for days with no requests.
     *
     * @return array<string, int>
     */
    public function dailyCounts(): array
    {
        $start = now()->subDays(self::DAYS - 1)->startOfDay();

        $counts = QuoteRequest::query()
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
                ['label' => 'Quote Requests', 'data' => array_values($counts)],
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
        $dates = json_encode(array_keys($this->dailyCounts()));
        $baseUrl = json_encode(QuoteRequestResource::getUrl('index'));

        return RawJs::make(<<<JS
            {
                onClick: (event, elements, chart) => {
                    if (! elements.length) {
                        return;
                    }

                    const dates = {$dates};
                    const date = dates[elements[0].index];

                    if (! date) {
                        return;
                    }

                    window.location.href = {$baseUrl} + '?tableFilters[date][date]=' + date;
                },
                plugins: {
                    legend: { display: false },
                },
            }
        JS);
    }
}

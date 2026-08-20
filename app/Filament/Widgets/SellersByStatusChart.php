<?php

namespace App\Filament\Widgets;

use App\Models\Seller;
use Filament\Widgets\ChartWidget;

class SellersByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Sellers by Status';

    protected static bool $isLazy = false;

    private const LABELS = [
        'pending_email_verification' => 'Pending Email Verification',
        'pending_admin_approval' => 'Pending Admin Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
    ];

    public static function canView(): bool
    {
        return auth('staff')->user()?->can('viewAny', Seller::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = Seller::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

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
                    'label' => 'Sellers',
                    'data' => array_values($this->statusCounts()),
                    'backgroundColor' => ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#64748b'],
                    'borderColor' => ['#d97706', '#2563eb', '#059669', '#dc2626', '#475569'],
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

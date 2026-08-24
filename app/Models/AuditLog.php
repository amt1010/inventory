<?php

namespace App\Models;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'importer_label', 'performed_by_staff_id', 'file_name',
        'total_rows', 'successful_rows', 'failed_rows', 'summary',
        'filament_import_id',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by_staff_id');
    }

    public static function recordCompletion(Import $import, string $summary): void
    {
        static::where('filament_import_id', $import->id)->update([
            'total_rows' => $import->total_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->getFailedRowsCount(),
            'summary' => $summary,
        ]);
    }
}

<?php

namespace App\Models;

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
}

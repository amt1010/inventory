<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Imports\CategoryProductImporter;
use App\Filament\Resources\ProductResource;
use App\Models\AuditLog;
use App\Services\CategoryProductXlsxImportRunner;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(CategoryProductImporter::class)
                ->label('Import Categories & Products'),
            // Filament's own Importer (above) is CSV-only in this version --
            // there is no v3 way to feed it .xlsx, so this is a fully separate
            // synchronous path built on CategoryProductXlsxImportRunner rather
            // than Filament's queued Import infrastructure.
            Actions\Action::make('importXlsx')
                ->label('Import from Excel (.xlsx)')
                ->icon('heroicon-o-document-arrow-up')
                ->form([
                    FileUpload::make('file')
                        ->label('Excel file (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('xlsx-imports')
                        ->preserveFilenames()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $relativePath = $data['file'];
                    $originalFileName = basename($relativePath);
                    $absolutePath = Storage::disk('local')->path($relativePath);

                    $result = (new CategoryProductXlsxImportRunner())->run($absolutePath);

                    Storage::disk('local')->delete($relativePath);

                    $summary = "{$result['created']} product(s) imported, {$result['skipped']} skipped, {$result['failed']} failed.";

                    AuditLog::create([
                        'importer_label' => 'Category & Product Import (Excel)',
                        'performed_by_staff_id' => auth('staff')->id(),
                        'file_name' => $originalFileName,
                        'total_rows' => $result['created'] + $result['skipped'] + $result['failed'],
                        'successful_rows' => $result['created'],
                        'failed_rows' => $result['failed'],
                        'summary' => $summary,
                    ]);

                    $notification = Notification::make()->title('Excel import complete')->body($summary);

                    if ($result['failed'] > 0) {
                        $notification->warning()->body($summary."\n".implode("\n", $result['errors']));
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}

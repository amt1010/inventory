<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_system'] = false;
        $data['subject'] = $data['draft_subject'];
        $data['body'] = $data['draft_body'];
        $data['default_cc'] = $data['draft_default_cc'] ?? null;
        $data['default_bcc'] = $data['draft_default_bcc'] ?? null;

        return $data;
    }
}

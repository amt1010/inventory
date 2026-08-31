<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->modalHeading('Preview')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (EmailTemplate $record) {
                    $tokens = \App\Filament\Resources\EmailTemplateResource::sampleTokensFor($record->key);
                    $renderer = app(\App\Services\EmailTemplateRenderer::class);

                    return view('filament.email-template-preview', [
                        'subject' => $renderer->render($record->draft_subject, $tokens),
                        'body' => $renderer->render($record->draft_body, $tokens),
                    ]);
                }),
            Action::make('publish')
                ->visible(fn (EmailTemplate $record) => $record->isModified())
                ->requiresConfirmation()
                ->action(function (EmailTemplate $record) {
                    $record->publish();

                    Notification::make()->title('Template published')->success()->send();
                }),
            Action::make('resetDraft')
                ->label('Reset Draft')
                ->visible(fn (EmailTemplate $record) => $record->isModified())
                ->requiresConfirmation()
                ->action(function (EmailTemplate $record) {
                    $record->resetDraft();

                    Notification::make()->title('Draft reset to the published version')->success()->send();
                }),
            DeleteAction::make()
                ->visible(fn (EmailTemplate $record) => ! $record->is_system),
        ];
    }
}

<?php

namespace App\Filament\Resources\QuoteRequestResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('note')->required()->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->columns([
                TextColumn::make('note')->wrap(),
                TextColumn::make('staff.name')->label('By'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['staff_id'] = auth('staff')->id();

                        return $data;
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

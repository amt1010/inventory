<?php

namespace App\Filament\Seller\Pages;

use App\Models\SellerDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Documents extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.seller.pages.documents';

    public function table(Table $table): Table
    {
        return $table
            ->query(SellerDocument::query()->where('seller_id', auth('seller')->id()))
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('uploaded_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('label')->required(),
                        FileUpload::make('file_path')->directory('seller-documents')->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['seller_id'] = auth('seller')->id();
                        $data['uploaded_at'] = now();

                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->defaultSort('uploaded_at', 'desc');
    }
}

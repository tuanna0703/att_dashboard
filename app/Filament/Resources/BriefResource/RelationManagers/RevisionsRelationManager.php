<?php

namespace App\Filament\Resources\BriefResource\RelationManagers;

use App\Models\BriefRevision;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';
    protected static ?string $title = 'Lịch sử Revision';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Nội dung Revision')->schema([
                Forms\Components\TextInput::make('revision_number')
                    ->label('Số revision')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\FileUpload::make('customer_file_path')
                    ->label('File brief từ khách')
                    ->directory('briefs/customer')
                    ->acceptedFileTypes(['application/pdf', 'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('planning_file_path')
                    ->label('File planning (AdOps)')
                    ->directory('briefs/planning')
                    ->acceptedFileTypes(['application/pdf', 'image/*',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('customer_note')
                    ->label('Yêu cầu điều chỉnh từ khách')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('adops_note')
                    ->label('Ghi chú AdOps')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('revision_number')
            ->columns([
                Tables\Columns\TextColumn::make('revision_number')
                    ->label('Rev.')
                    ->sortable()
                    ->weight('bold')
                    ->prefix('#'),

                Tables\Columns\IconColumn::make('customer_file_path')
                    ->label('File KH')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->getStateUsing(fn ($record) => (bool) $record->customer_file_path),

                Tables\Columns\IconColumn::make('planning_file_path')
                    ->label('File Planning')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->getStateUsing(fn ($record) => (bool) $record->planning_file_path),

                Tables\Columns\TextColumn::make('adops_note')
                    ->label('Ghi chú AdOps')
                    ->limit(50)
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($state) => BriefRevision::$statuses[$state] ?? $state)
                    ->colors(BriefRevision::$statusColors),

                Tables\Columns\IconColumn::make('is_final')
                    ->label('Cuối cùng')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning'),

                Tables\Columns\TextColumn::make('sentBy.name')
                    ->label('Gửi bởi')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Ngày gửi')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('revision_number', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Thêm Revision')
                    ->mutateFormDataUsing(function (array $data): array {
                        $brief = $this->getOwnerRecord();
                        $data['revision_number'] = $brief->revisions()->max('revision_number') + 1;
                        $data['status'] = 'draft';
                        return $data;
                    })
                    ->after(function ($record) {
                        // Đánh dấu revision cũ là superseded
                        $this->getOwnerRecord()
                            ->revisions()
                            ->where('id', '!=', $record->id)
                            ->whereNotIn('status', ['approved', 'rejected'])
                            ->update(['status' => 'superseded']);

                        $this->getOwnerRecord()->update(['current_revision_id' => $record->id]);
                    })
                    ->visible(fn () => ! in_array($this->getOwnerRecord()->status, ['confirmed', 'converted', 'rejected'])),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (BriefRevision $record) => $record->status === 'draft'),

                    // Gửi cho khách hàng
                    Tables\Actions\Action::make('send_to_customer')
                        ->label('Gửi khách hàng')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->visible(fn (BriefRevision $record) => in_array($record->status, ['draft']))
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận gửi planning cho khách hàng?')
                        ->action(function (BriefRevision $record) {
                            $record->update([
                                'status'  => 'sent_to_customer',
                                'sent_by' => auth()->id(),
                                'sent_at' => now(),
                            ]);
                            $record->brief->update(['status' => 'sent_to_customer']);
                            Notification::make()->title('Đã gửi planning cho khách hàng')->success()->send();
                        }),

                    // Khách phản hồi
                    Tables\Actions\Action::make('customer_feedback')
                        ->label('Ghi nhận phản hồi KH')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->color('warning')
                        ->visible(fn (BriefRevision $record) => $record->status === 'sent_to_customer')
                        ->form([
                            Forms\Components\Textarea::make('customer_note')
                                ->label('Phản hồi / yêu cầu điều chỉnh từ khách')
                                ->required()
                                ->rows(4),
                        ])
                        ->modalHeading('Ghi nhận phản hồi khách hàng')
                        ->action(function (BriefRevision $record, array $data) {
                            $record->update([
                                'status'        => 'customer_feedback',
                                'customer_note' => $data['customer_note'],
                            ]);
                            $record->brief->update(['status' => 'customer_feedback']);
                            Notification::make()->title('Đã ghi nhận phản hồi khách hàng')->warning()->send();
                        }),

                    // Khách confirm revision này
                    Tables\Actions\Action::make('approve_revision')
                        ->label('Khách confirm')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (BriefRevision $record) => $record->status === 'sent_to_customer')
                        ->requiresConfirmation()
                        ->modalHeading('Xác nhận khách hàng đã approve revision này?')
                        ->action(function (BriefRevision $record) {
                            $record->update(['status' => 'approved', 'is_final' => true]);
                            // Supersede các revision khác
                            $record->brief->revisions()
                                ->where('id', '!=', $record->id)
                                ->update(['is_final' => false]);
                            $record->brief->update([
                                'status'              => 'confirmed',
                                'current_revision_id' => $record->id,
                            ]);
                            Notification::make()->title('Brief đã được khách confirm')->success()->send();
                        }),

                    // Khách reject
                    Tables\Actions\Action::make('reject_revision')
                        ->label('Khách từ chối')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (BriefRevision $record) => $record->status === 'sent_to_customer')
                        ->form([
                            Forms\Components\Textarea::make('customer_note')
                                ->label('Lý do từ chối')
                                ->required()
                                ->rows(3),
                        ])
                        ->modalHeading('Ghi nhận khách hàng từ chối')
                        ->action(function (BriefRevision $record, array $data) {
                            $record->update([
                                'status'        => 'rejected',
                                'customer_note' => $data['customer_note'],
                            ]);
                            $record->brief->update(['status' => 'rejected']);
                            Notification::make()->title('Revision bị từ chối — cần tạo revision mới')->danger()->send();
                        }),
                ]),
            ]);
    }
}

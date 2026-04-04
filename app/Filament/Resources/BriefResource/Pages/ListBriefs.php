<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use App\Models\Brief;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBriefs extends ListRecords
{
    protected static string $resource = BriefResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('+ Tạo Brief')];
    }

    protected function getTableQuery(): Builder
    {
        $query = Brief::query();
        $user  = auth()->user();

        if ($user->hasRole('sale')) {
            $query->where('sale_id', $user->id);
        } elseif ($user->hasRole('adops')) {
            $query->where('adops_id', $user->id);
        }
        // CEO, COO và các role khác thấy tất cả

        return $query;
    }
}

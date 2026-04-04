<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    /** Brief IDs whose older versions are currently expanded. */
    public array $expandedBriefs = [];

    public function toggleBriefExpansion(int $briefId): void
    {
        if (($key = array_search($briefId, $this->expandedBriefs)) !== false) {
            array_splice($this->expandedBriefs, $key, 1);
        } else {
            $this->expandedBriefs[] = $briefId;
        }

        $this->resetTable();
    }

    /**
     * Default: show only the latest version per brief.
     * When a brief is expanded: also show all older versions (newest first).
     */
    protected function getTableQuery(): Builder
    {
        $expandedBriefs = $this->expandedBriefs;
        $user           = auth()->user();

        $query = Plan::query()
            ->selectRaw(
                'plans.*, ' .
                '(SELECT MAX(p2.version) FROM plans p2 ' .
                ' WHERE p2.brief_id = plans.brief_id AND p2.deleted_at IS NULL) AS max_version'
            )
            ->where(function (Builder $q) use ($expandedBriefs) {
                // Always show the latest version for every brief
                $q->whereRaw(
                    'plans.version = (SELECT MAX(p2.version) FROM plans p2 ' .
                    'WHERE p2.brief_id = plans.brief_id AND p2.deleted_at IS NULL)'
                );
                // Also show ALL versions for expanded briefs
                if (!empty($expandedBriefs)) {
                    $q->orWhereIn('plans.brief_id', $expandedBriefs);
                }
            })
            // Group latest-per-brief by recency, then show older versions immediately below
            ->orderByRaw(
                '(SELECT MAX(p3.created_at) FROM plans p3 WHERE p3.brief_id = plans.brief_id) DESC'
            )
            ->orderByDesc('plans.version');

        // Scope theo role: sale thấy plans của brief mình phụ trách,
        // adops thấy plans mình được assign
        if ($user->hasRole('sale')) {
            $query->whereHas('brief', fn (Builder $q) => $q->where('sale_id', $user->id));
        } elseif ($user->hasRole('adops')) {
            $query->where('plans.adops_id', $user->id);
        }
        // CEO, COO và các role khác thấy tất cả

        return $query;
    }
}

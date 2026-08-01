<?php

declare(strict_types=1);

namespace Modules\Pipeline\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Pipeline\Models\PipelineRun;

final class ListPipelineRuns
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PipelineRun>
     */
    public function handle(int $ownerId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return PipelineRun::query()
            ->where('owner_id', $ownerId)
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['trigger_source']), fn ($query) => $query->where('trigger_source', $filters['trigger_source']))
            ->when(isset($filters['definition_key']), fn ($query) => $query->where('definition_key', $filters['definition_key']))
            ->when(isset($filters['from']), fn ($query) => $query->where('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->where('created_at', '<=', $filters['to']))
            ->with(['steps'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

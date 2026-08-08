<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Ai\Models\AiUsageLog;

final class SummariseAiUsage
{
    /**
     * @return array<string, mixed>
     */
    public function handle(int $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        $base = AiUsageLog::query()
            ->where('owner_id', $userId)
            ->whereBetween('created_at', [$from, $to]);

        /** @var object{input_tokens: int|null, output_tokens: int|null, cached_input_tokens: int|null, cost_usd_micros: int|null}|null $totals */
        $totals = (clone $base)
            ->selectRaw('COALESCE(SUM(input_tokens), 0) AS input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) AS output_tokens')
            ->selectRaw('COALESCE(SUM(cached_input_tokens), 0) AS cached_input_tokens')
            ->selectRaw('COALESCE(SUM(cost_usd_micros), 0) AS cost_usd_micros')
            ->first();

        $byModel = (clone $base)
            ->select('provider', 'model')
            ->selectRaw('COUNT(*) AS calls')
            ->selectRaw('SUM(input_tokens) AS input_tokens')
            ->selectRaw('SUM(output_tokens) AS output_tokens')
            ->selectRaw('SUM(cost_usd_micros) AS cost_usd_micros')
            ->groupBy('provider', 'model')
            ->orderByDesc(DB::raw('SUM(cost_usd_micros)'))
            ->get();

        return [
            'totals' => [
                'input_tokens' => (int) ($totals->input_tokens ?? 0),
                'output_tokens' => (int) ($totals->output_tokens ?? 0),
                'cached_input_tokens' => (int) ($totals->cached_input_tokens ?? 0),
                'cost_usd_micros' => (int) ($totals->cost_usd_micros ?? 0),
            ],
            'by_model' => $byModel->map(fn ($row): array => [
                'provider' => (string) $row->provider,
                'model' => (string) $row->model,
                // @phpstan-ignore property.notFound (an aggregate alias from selectRaw, not a column on the model)
                'calls' => (int) $row->calls,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost_usd_micros' => (int) $row->cost_usd_micros,
            ])->all(),
        ];
    }
}

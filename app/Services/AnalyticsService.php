<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Message;
use App\Models\ConversationSlaLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getConversationMetrics(int $organizationId, Carbon $from, Carbon $to): array
    {
        $base = Conversation::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();
        $open = (clone $base)->where('status', 'open')->count();
        $closed = (clone $base)->where('status', 'closed')->count();

        $byChannel = Conversation::where('conversations.organization_id', $organizationId)
            ->whereBetween('conversations.created_at', [$from, $to])
            ->join('channels', 'conversations.channel_id', '=', 'channels.id')
            ->select('channels.type', DB::raw('COUNT(*) as total'))
            ->groupBy('channels.type')
            ->pluck('total', 'type')
            ->toArray();

        $byPriority = (clone $base)
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $avgResolutionSeconds = (clone $base)
            ->whereNotNull('closed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (closed_at - created_at))) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total' => $total,
            'open' => $open,
            'closed' => $closed,
            'by_channel' => $byChannel,
            'by_priority' => $byPriority,
            'avg_resolution_minutes' => $avgResolutionSeconds ? round($avgResolutionSeconds / 60, 1) : null,
        ];
    }

    public function getMessageMetrics(int $organizationId, Carbon $from, Carbon $to): array
    {
        $base = Message::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();
        $inbound = (clone $base)->where('direction', 'inbound')->count();
        $outbound = (clone $base)->where('direction', 'outbound')->count();

        $dailyVolume = (clone $base)
            ->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        return [
            'total' => $total,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'daily_volume' => $dailyVolume,
        ];
    }

    public function getDealMetrics(int $organizationId, Carbon $from, Carbon $to): array
    {
        $base = Deal::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();
        $won = (clone $base)->where('status', 'won')->count();
        $lost = (clone $base)->where('status', 'lost')->count();
        $open = (clone $base)->where('status', 'open')->count();

        $totalValue = (clone $base)->sum('value');
        $wonValue = (clone $base)->where('status', 'won')->sum('value');

        $conversionRate = $total > 0 ? round(($won / $total) * 100, 1) : 0;

        $avgCloseSeconds = (clone $base)
            ->whereNotNull('closed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (closed_at - created_at))) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total' => $total,
            'open' => $open,
            'won' => $won,
            'lost' => $lost,
            'total_value' => round($totalValue, 2),
            'won_value' => round($wonValue, 2),
            'conversion_rate' => $conversionRate,
            'avg_close_days' => $avgCloseSeconds ? round($avgCloseSeconds / 86400, 1) : null,
        ];
    }

    public function getAgentMetrics(int $organizationId, Carbon $from, Carbon $to): array
    {
        $agents = Conversation::where('conversations.organization_id', $organizationId)
            ->whereBetween('conversations.created_at', [$from, $to])
            ->whereNotNull('assigned_user_id')
            ->join('users', 'conversations.assigned_user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as total_conversations'),
                DB::raw("SUM(CASE WHEN conversations.status = 'closed' THEN 1 ELSE 0 END) as closed_conversations"),
                DB::raw("AVG(CASE WHEN conversations.closed_at IS NOT NULL THEN EXTRACT(EPOCH FROM (conversations.closed_at - conversations.created_at)) END) as avg_resolution_seconds")
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_conversations')
            ->get()
            ->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'total_conversations' => $agent->total_conversations,
                    'closed_conversations' => $agent->closed_conversations,
                    'avg_resolution_minutes' => $agent->avg_resolution_seconds
                        ? round($agent->avg_resolution_seconds / 60, 1)
                        : null,
                ];
            })
            ->toArray();

        return $agents;
    }

    public function getSlaMetrics(int $organizationId, Carbon $from, Carbon $to): array
    {
        $base = ConversationSlaLog::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();
        $breached = (clone $base)->where('breached', true)->count();
        $complianceRate = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100;

        return [
            'total' => $total,
            'breached' => $breached,
            'compliant' => $total - $breached,
            'compliance_rate' => $complianceRate,
        ];
    }

    public function getConversationTrend(int $organizationId, Carbon $from, Carbon $to): array
    {
        return Conversation::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();
    }

    public function exportCsvData(int $organizationId, Carbon $from, Carbon $to): array
    {
        $conversations = $this->getConversationMetrics($organizationId, $from, $to);
        $messages = $this->getMessageMetrics($organizationId, $from, $to);
        $deals = $this->getDealMetrics($organizationId, $from, $to);
        $agents = $this->getAgentMetrics($organizationId, $from, $to);
        $sla = $this->getSlaMetrics($organizationId, $from, $to);

        return [
            'conversations' => $conversations,
            'messages' => $messages,
            'deals' => $deals,
            'agents' => $agents,
            'sla' => $sla,
        ];
    }
}

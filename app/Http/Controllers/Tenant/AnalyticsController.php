<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private AnalyticsService $analyticsService)
    {
    }

    public function index(Request $request)
    {
        if (! $request->user()->can('reports.view')) {
            abort(403);
        }

        $tenant = app('tenant');
        [$from, $to] = $this->resolvePeriod($request);

        $conversations = $this->analyticsService->getConversationMetrics($tenant->id, $from, $to);
        $messages = $this->analyticsService->getMessageMetrics($tenant->id, $from, $to);
        $deals = $this->analyticsService->getDealMetrics($tenant->id, $from, $to);
        $agents = $this->analyticsService->getAgentMetrics($tenant->id, $from, $to);
        $sla = $this->analyticsService->getSlaMetrics($tenant->id, $from, $to);
        $conversationTrend = $this->analyticsService->getConversationTrend($tenant->id, $from, $to);

        return view('analytics.index', compact(
            'conversations',
            'messages',
            'deals',
            'agents',
            'sla',
            'conversationTrend',
            'from',
            'to',
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        if (! $request->user()->can('reports.view')) {
            abort(403);
        }

        $tenant = app('tenant');
        [$from, $to] = $this->resolvePeriod($request);
        $data = $this->analyticsService->exportCsvData($tenant->id, $from, $to);

        $filename = "chatme_report_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv";

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // Conversations section
            fputcsv($handle, ['--- CONVERSACIONES ---']);
            fputcsv($handle, ['Metrica', 'Valor']);
            fputcsv($handle, ['Total', $data['conversations']['total']]);
            fputcsv($handle, ['Abiertas', $data['conversations']['open']]);
            fputcsv($handle, ['Cerradas', $data['conversations']['closed']]);
            fputcsv($handle, ['Resolucion Promedio (min)', $data['conversations']['avg_resolution_minutes'] ?? 'N/A']);
            fputcsv($handle, []);

            // By channel
            fputcsv($handle, ['--- POR CANAL ---']);
            fputcsv($handle, ['Canal', 'Total']);
            foreach ($data['conversations']['by_channel'] as $channel => $total) {
                fputcsv($handle, [$channel, $total]);
            }
            fputcsv($handle, []);

            // Messages section
            fputcsv($handle, ['--- MENSAJES ---']);
            fputcsv($handle, ['Metrica', 'Valor']);
            fputcsv($handle, ['Total', $data['messages']['total']]);
            fputcsv($handle, ['Entrantes', $data['messages']['inbound']]);
            fputcsv($handle, ['Salientes', $data['messages']['outbound']]);
            fputcsv($handle, []);

            // Deals section
            fputcsv($handle, ['--- NEGOCIOS ---']);
            fputcsv($handle, ['Metrica', 'Valor']);
            fputcsv($handle, ['Total', $data['deals']['total']]);
            fputcsv($handle, ['Abiertos', $data['deals']['open']]);
            fputcsv($handle, ['Ganados', $data['deals']['won']]);
            fputcsv($handle, ['Perdidos', $data['deals']['lost']]);
            fputcsv($handle, ['Valor Total', $data['deals']['total_value']]);
            fputcsv($handle, ['Valor Ganado', $data['deals']['won_value']]);
            fputcsv($handle, ['Tasa Conversion (%)', $data['deals']['conversion_rate']]);
            fputcsv($handle, ['Cierre Promedio (dias)', $data['deals']['avg_close_days'] ?? 'N/A']);
            fputcsv($handle, []);

            // Agents section
            fputcsv($handle, ['--- AGENTES ---']);
            fputcsv($handle, ['Nombre', 'Conversaciones', 'Cerradas', 'Resolucion Prom. (min)']);
            foreach ($data['agents'] as $agent) {
                fputcsv($handle, [
                    $agent['name'],
                    $agent['total_conversations'],
                    $agent['closed_conversations'],
                    $agent['avg_resolution_minutes'] ?? 'N/A',
                ]);
            }
            fputcsv($handle, []);

            // SLA section
            fputcsv($handle, ['--- SLA ---']);
            fputcsv($handle, ['Metrica', 'Valor']);
            fputcsv($handle, ['Total', $data['sla']['total']]);
            fputcsv($handle, ['Incumplidos', $data['sla']['breached']]);
            fputcsv($handle, ['Cumplidos', $data['sla']['compliant']]);
            fputcsv($handle, ['Tasa Cumplimiento (%)', $data['sla']['compliance_rate']]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $period = $request->input('period', '7d');

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '30d' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            'custom' => [
                Carbon::parse($request->input('from', now()->subDays(7)->toDateString()))->startOfDay(),
                Carbon::parse($request->input('to', now()->toDateString()))->endOfDay(),
            ],
            default => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
        };
    }
}

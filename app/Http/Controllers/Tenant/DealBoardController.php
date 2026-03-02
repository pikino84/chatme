<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class DealBoardController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();

        $pipelines = Pipeline::orderBy('name')->get();

        $activePipeline = $request->filled('pipeline_id')
            ? Pipeline::findOrFail($request->input('pipeline_id'))
            : Pipeline::where('is_default', true)->first() ?? $pipelines->first();

        if (! $activePipeline) {
            return view('deals.board', [
                'pipelines' => $pipelines,
                'activePipeline' => null,
                'stages' => collect(),
                'agents' => collect(),
            ]);
        }

        $this->authorize('view', $activePipeline);

        $stages = $activePipeline->stages()
            ->orderBy('position')
            ->with(['deals' => function ($q) use ($user) {
                $q->with(['assignedUser', 'tags'])
                    ->where('status', 'open');

                if (! $user->hasPermissionTo('deals.view-all')) {
                    $q->where('assigned_user_id', $user->id);
                }

                $q->orderBy('stage_entered_at');
            }])
            ->get();

        $agents = User::where('organization_id', $user->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('deals.board', compact('pipelines', 'activePipeline', 'stages', 'agents'));
    }

    public function show(Request $request, Deal $deal)
    {
        $this->authorize('view', $deal);

        $user = $request->user();

        $deal->load([
            'pipeline',
            'stage',
            'assignedUser',
            'tags',
            'notes' => fn ($q) => $q->with('user')->latest(),
            'stageHistory' => fn ($q) => $q->with(['fromStage', 'toStage', 'changedByUser'])->latest('changed_at'),
            'conversation',
        ]);

        $pipelines = Pipeline::orderBy('name')->get();
        $dealStages = $deal->pipeline->stages()->orderBy('position')->get();

        $stages = $deal->pipeline->stages()
            ->orderBy('position')
            ->with(['deals' => function ($q) use ($user) {
                $q->with(['assignedUser', 'tags'])
                    ->where('status', 'open');

                if (! $user->hasPermissionTo('deals.view-all')) {
                    $q->where('assigned_user_id', $user->id);
                }

                $q->orderBy('stage_entered_at');
            }])
            ->get();

        $agents = User::where('organization_id', $user->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('deals.board', [
            'pipelines' => $pipelines,
            'activePipeline' => $deal->pipeline,
            'stages' => $stages,
            'agents' => $agents,
            'selectedDeal' => $deal,
            'dealStages' => $dealStages,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Tag;
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
            'attachments' => fn ($q) => $q->with('user')->latest(),
            'commissions' => fn ($q) => $q->with('user')->latest(),
            'stageHistory' => fn ($q) => $q->with(['fromStage', 'toStage', 'changedByUser'])->latest('changed_at'),
            'conversation',
        ]);

        // JSON response for AJAX drawer
        if ($request->wantsJson()) {
            $dealStages = $deal->pipeline->stages()->orderBy('position')->get();
            $agents = User::where('organization_id', $user->organization_id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            $allTags = Tag::orderBy('name')->get();

            return response()->json([
                'deal' => [
                    'id' => $deal->id,
                    'contact_name' => $deal->contact_name,
                    'contact_email' => $deal->contact_email,
                    'contact_phone' => $deal->contact_phone,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'status' => $deal->status,
                    'expected_close_date' => $deal->expected_close_date?->format('M d, Y'),
                    'pipeline_id' => $deal->pipeline_id,
                    'pipeline_stage_id' => $deal->pipeline_stage_id,
                    'assigned_user_id' => $deal->assigned_user_id,
                    'pipeline' => ['name' => $deal->pipeline->name],
                    'stage' => ['name' => $deal->stage->name, 'color' => $deal->stage->color ?? '#6B7280'],
                    'assigned_user' => $deal->assignedUser ? ['name' => $deal->assignedUser->name] : null,
                    'tags' => $deal->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
                    'notes' => $deal->notes->map(fn ($n) => [
                        'body' => $n->body,
                        'user' => $n->user->name,
                        'created_at' => $n->created_at->diffForHumans(),
                    ]),
                    'attachments' => $deal->attachments->map(fn ($a) => [
                        'id' => $a->id,
                        'file_name' => $a->file_name,
                        'size' => $a->sizeForHumans(),
                        'user' => $a->user->name,
                        'url' => $a->url(),
                        'created_at' => $a->created_at->diffForHumans(),
                    ]),
                    'commissions' => $deal->commissions->map(fn ($c) => [
                        'id' => $c->id,
                        'user' => $c->user->name,
                        'percentage' => $c->percentage,
                        'amount' => $c->amount,
                        'status' => $c->status,
                    ]),
                    'stage_history' => $deal->stageHistory->map(fn ($h) => [
                        'from' => $h->fromStage?->name ?? 'Creado',
                        'to' => $h->toStage->name,
                        'by' => $h->changedByUser?->name,
                        'at' => $h->changed_at->diffForHumans(),
                    ]),
                    'conversation' => $deal->conversation ? [
                        'url' => route('inbox.conversations.show', $deal->conversation),
                        'label' => $deal->conversation->contact_name ?? $deal->conversation->contact_identifier,
                    ] : null,
                ],
                'stages' => $dealStages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
                'agents' => $agents,
                'all_tags' => $allTags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
                'can_update' => $user->can('update', $deal),
                'can_assign' => $user->can('assign', $deal),
                'can_manage_commissions' => $user->hasPermissionTo('deals.manage-commissions'),
            ]);
        }

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

        $allTags = Tag::orderBy('name')->get();

        return view('deals.board', [
            'pipelines' => $pipelines,
            'activePipeline' => $deal->pipeline,
            'stages' => $stages,
            'agents' => $agents,
            'selectedDeal' => $deal,
            'dealStages' => $dealStages,
            'allTags' => $allTags,
        ]);
    }
}

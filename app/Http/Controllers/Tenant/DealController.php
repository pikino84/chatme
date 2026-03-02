<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealNote;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class DealController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private DealService $dealService) {}

    public function store(Request $request)
    {
        $this->authorize('create', Deal::class);

        $validated = $request->validate([
            'pipeline_id' => 'required|exists:pipelines,id',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'value' => 'nullable|numeric|min:0',
            'expected_close_date' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        Pipeline::findOrFail($validated['pipeline_id']);

        if (! empty($validated['assigned_user_id'])) {
            $assignee = User::findOrFail($validated['assigned_user_id']);
            if ($assignee->organization_id !== $request->user()->organization_id) {
                abort(403, 'Cannot assign to user from another organization.');
            }
        }

        $deal = $this->dealService->createDeal(array_merge($validated, [
            'organization_id' => $request->user()->organization_id,
        ]), $request->user());

        return redirect()->route('deals.show', $deal)->with('success', 'Deal created.');
    }

    public function move(Request $request, Deal $deal)
    {
        $this->authorize('update', $deal);

        $request->validate([
            'pipeline_stage_id' => 'required|exists:pipeline_stages,id',
        ]);

        $newStage = PipelineStage::findOrFail($request->input('pipeline_stage_id'));

        if ($newStage->pipeline_id !== $deal->pipeline_id) {
            abort(422, 'Stage does not belong to this pipeline.');
        }

        $this->dealService->moveToStage($deal, $newStage, $request->user());

        return back()->with('success', "Deal moved to {$newStage->name}.");
    }

    public function assign(Request $request, Deal $deal)
    {
        $this->authorize('assign', $deal);

        $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
        ]);

        $assignee = User::findOrFail($request->input('assigned_user_id'));

        if ($assignee->organization_id !== $request->user()->organization_id) {
            abort(403, 'Cannot assign to user from another organization.');
        }

        $deal->update(['assigned_user_id' => $assignee->id]);

        return back()->with('success', "Deal assigned to {$assignee->name}.");
    }

    public function addNote(Request $request, Deal $deal)
    {
        $this->authorize('view', $deal);
        $this->authorize('create', DealNote::class);

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $this->dealService->addNote($deal, $request->user(), $request->input('body'));

        return back()->with('success', 'Note added.');
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\DealService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private DealService $dealService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Pipeline::class);

        $pipelines = Pipeline::withCount(['stages', 'deals'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('pipelines.index', compact('pipelines'));
    }

    public function create()
    {
        $this->authorize('create', Pipeline::class);

        $stagesJson = [
            ['id' => null, 'name' => 'Nuevo', 'color' => '#3B82F6', 'is_won' => false, 'is_lost' => false, 'max_duration_hours' => null, 'has_deals' => false],
            ['id' => null, 'name' => 'En Progreso', 'color' => '#F59E0B', 'is_won' => false, 'is_lost' => false, 'max_duration_hours' => null, 'has_deals' => false],
            ['id' => null, 'name' => 'Ganado', 'color' => '#10B981', 'is_won' => true, 'is_lost' => false, 'max_duration_hours' => null, 'has_deals' => false],
            ['id' => null, 'name' => 'Perdido', 'color' => '#EF4444', 'is_won' => false, 'is_lost' => true, 'max_duration_hours' => null, 'has_deals' => false],
        ];

        return view('pipelines.form', ['pipeline' => null, 'stages' => [], 'stagesJson' => $stagesJson]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Pipeline::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stages' => 'required|array|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.color' => 'nullable|string|max:7',
            'stages.*.is_won' => 'nullable|boolean',
            'stages.*.is_lost' => 'nullable|boolean',
            'stages.*.max_duration_hours' => 'nullable|integer|min:1',
        ]);

        $pipeline = Pipeline::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'is_default' => !Pipeline::where('is_default', true)->exists(),
        ]);

        foreach ($validated['stages'] as $index => $stageData) {
            PipelineStage::create([
                'organization_id' => $request->user()->organization_id,
                'pipeline_id' => $pipeline->id,
                'name' => $stageData['name'],
                'position' => $index + 1,
                'color' => $stageData['color'] ?? '#6B7280',
                'is_won' => !empty($stageData['is_won']),
                'is_lost' => !empty($stageData['is_lost']),
                'max_duration_hours' => $stageData['max_duration_hours'] ?? null,
            ]);
        }

        return redirect()->route('pipelines.index')->with('success', 'Pipeline creado exitosamente.');
    }

    public function edit(Pipeline $pipeline)
    {
        $this->authorize('update', $pipeline);

        $stages = $pipeline->stages()->orderBy('position')->get();

        $stagesJson = $stages->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color ?? '#6B7280',
                'is_won' => $s->is_won,
                'is_lost' => $s->is_lost,
                'max_duration_hours' => $s->max_duration_hours,
                'has_deals' => $s->deals()->count() > 0,
            ];
        })->values()->toArray();

        return view('pipelines.form', compact('pipeline', 'stages', 'stagesJson'));
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $this->authorize('update', $pipeline);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stages' => 'required|array|min:1',
            'stages.*.id' => 'nullable|integer',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.color' => 'nullable|string|max:7',
            'stages.*.is_won' => 'nullable|boolean',
            'stages.*.is_lost' => 'nullable|boolean',
            'stages.*.max_duration_hours' => 'nullable|integer|min:1',
        ]);

        $pipeline->update(['name' => $validated['name']]);

        $existingStageIds = $pipeline->stages()->pluck('id')->toArray();
        $submittedIds = array_filter(array_column($validated['stages'], 'id'));

        // Delete removed stages (only if no deals)
        $toDelete = array_diff($existingStageIds, $submittedIds);
        foreach ($toDelete as $stageId) {
            $stage = PipelineStage::find($stageId);
            if ($stage && $stage->deals()->count() === 0) {
                $stage->delete();
            }
        }

        // Update or create stages
        foreach ($validated['stages'] as $index => $stageData) {
            $attrs = [
                'name' => $stageData['name'],
                'position' => $index + 1,
                'color' => $stageData['color'] ?? '#6B7280',
                'is_won' => !empty($stageData['is_won']),
                'is_lost' => !empty($stageData['is_lost']),
                'max_duration_hours' => $stageData['max_duration_hours'] ?? null,
            ];

            if (!empty($stageData['id'])) {
                PipelineStage::where('id', $stageData['id'])
                    ->where('pipeline_id', $pipeline->id)
                    ->update($attrs);
            } else {
                PipelineStage::create(array_merge($attrs, [
                    'organization_id' => $request->user()->organization_id,
                    'pipeline_id' => $pipeline->id,
                ]));
            }
        }

        return redirect()->route('pipelines.index')->with('success', 'Pipeline actualizado exitosamente.');
    }

    public function destroy(Pipeline $pipeline)
    {
        $this->authorize('delete', $pipeline);

        if ($pipeline->deals()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un pipeline que tiene negocios.');
        }

        $pipeline->stages()->delete();
        $pipeline->delete();

        return redirect()->route('pipelines.index')->with('success', 'Pipeline eliminado.');
    }

    public function setDefault(Pipeline $pipeline)
    {
        $this->authorize('update', $pipeline);

        $this->dealService->setDefaultPipeline($pipeline);

        return back()->with('success', "'{$pipeline->name}' es ahora el pipeline predeterminado.");
    }
}

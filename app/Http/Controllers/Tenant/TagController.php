<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TagController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $request->user()->hasPermissionTo('pipelines.view') || abort(403);

        $tags = Tag::withCount('deals')->latest()->get();

        return view('tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $request->user()->hasPermissionTo('pipelines.create') || abort(403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $exists = Tag::where('name', $validated['name'])->exists();
        if ($exists) {
            return back()->with('error', 'Ya existe una etiqueta con ese nombre.');
        }

        Tag::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6B7280',
        ]);

        return back()->with('success', 'Etiqueta creada.');
    }

    public function update(Request $request, Tag $tag)
    {
        $request->user()->hasPermissionTo('pipelines.update') || abort(403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $exists = Tag::where('name', $validated['name'])->where('id', '!=', $tag->id)->exists();
        if ($exists) {
            return back()->with('error', 'Ya existe otra etiqueta con ese nombre.');
        }

        $tag->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $tag->color,
        ]);

        return back()->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Tag $tag)
    {
        request()->user()->hasPermissionTo('pipelines.delete') || abort(403);

        $tag->deals()->detach();
        $tag->delete();

        return back()->with('success', 'Etiqueta eliminada.');
    }
}

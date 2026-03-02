<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\KbCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class KbCategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        if (! $request->user()->hasPermissionTo('kb.view')) {
            abort(403);
        }

        $categories = KbCategory::withCount('articles')
            ->orderBy('position')
            ->get();

        return view('kb.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        if (! $request->user()->hasPermissionTo('kb.create')) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'position' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['organization_id'] = app('tenant')->id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['position'] = $data['position'] ?? 0;

        KbCategory::create($data);

        return redirect()->route('kb.categories')->with('success', 'Category created.');
    }

    public function update(Request $request, KbCategory $category)
    {
        $this->authorize('update', $category);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'position' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');

        $category->update($data);

        return redirect()->route('kb.categories')->with('success', 'Category updated.');
    }

    public function destroy(KbCategory $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()->route('kb.categories')->with('success', 'Category deleted.');
    }
}

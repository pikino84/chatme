<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        if (! $request->user()->hasPermissionTo('brands.view')) {
            abort(403);
        }

        $brands = Brand::withCount(['branches', 'channels', 'conversations', 'kbArticles'])
            ->orderBy('name')
            ->get();

        return view('brands.index', compact('brands'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Brand::class);

        return view('brands.form', ['brand' => null]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Brand::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:7',
            'ai_context' => 'nullable|string|max:5000',
        ]);

        $data['organization_id'] = app('tenant')->id;

        $settings = [];
        if ($request->filled('ai_context')) {
            $settings['ai_context'] = $data['ai_context'];
        }
        unset($data['ai_context']);
        $data['settings'] = $settings ?: null;

        Brand::create($data);

        return redirect()->route('brands.index')->with('success', 'Marca creada exitosamente.');
    }

    public function show(Brand $brand)
    {
        $this->authorize('view', $brand);

        $brand->load([
            'branches',
            'channels' => fn ($q) => $q->withCount('conversations'),
            'kbCategories' => fn ($q) => $q->withCount('articles'),
        ]);

        $brand->loadCount(['conversations', 'kbArticles']);

        return view('brands.show', compact('brand'));
    }

    public function edit(Brand $brand)
    {
        $this->authorize('update', $brand);

        return view('brands.form', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean',
            'ai_context' => 'nullable|string|max:5000',
        ]);

        $settings = $brand->settings ?? [];
        if ($request->filled('ai_context')) {
            $settings['ai_context'] = $data['ai_context'];
        } else {
            unset($settings['ai_context']);
        }
        unset($data['ai_context']);

        $data['settings'] = $settings ?: null;
        $data['is_active'] = $request->has('is_active');

        $brand->update($data);

        return redirect()->route('brands.show', $brand)->with('success', 'Marca actualizada.');
    }

    public function destroy(Brand $brand)
    {
        $this->authorize('delete', $brand);

        if ($brand->conversations()->exists()) {
            return redirect()->route('brands.show', $brand)
                ->with('error', 'No se puede eliminar una marca con conversaciones. Desactívala en su lugar.');
        }

        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Marca eliminada.');
    }
}

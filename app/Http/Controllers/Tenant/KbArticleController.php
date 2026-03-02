<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Services\KnowledgeBaseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class KbArticleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private KnowledgeBaseService $kbService) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPermissionTo('kb.view')) {
            abort(403);
        }

        $query = KbArticle::with('category', 'creator');

        if ($request->filled('category_id')) {
            $query->where('kb_category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $articles = $query->latest('updated_at')->paginate(25);
        $categories = KbCategory::orderBy('position')->get();

        return view('kb.articles', compact('articles', 'categories'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', KbArticle::class);

        $categories = KbCategory::orderBy('position')->get();

        return view('kb.article-form', [
            'article' => null,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', KbArticle::class);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'kb_category_id' => 'required|exists:kb_categories,id',
            'priority' => 'nullable|integer|min:0|max:100',
            'visible_on_webchat' => 'nullable|boolean',
            'visible_on_whatsapp' => 'nullable|boolean',
            'visible_on_instagram' => 'nullable|boolean',
            'visible_on_facebook' => 'nullable|boolean',
        ]);

        $data['organization_id'] = app('tenant')->id;
        $data['priority'] = $data['priority'] ?? 0;
        $data['visible_on_webchat'] = $request->has('visible_on_webchat');
        $data['visible_on_whatsapp'] = $request->has('visible_on_whatsapp');
        $data['visible_on_instagram'] = $request->has('visible_on_instagram');
        $data['visible_on_facebook'] = $request->has('visible_on_facebook');

        $this->kbService->createArticle($data, $request->user());

        return redirect()->route('kb.articles')->with('success', 'Article created.');
    }

    public function show(KbArticle $article)
    {
        $this->authorize('view', $article);

        $article->load(['category', 'creator', 'versions' => fn ($q) => $q->orderByDesc('version_number')]);
        $article->versions->load('changedByUser');

        return view('kb.article-show', compact('article'));
    }

    public function edit(KbArticle $article)
    {
        $this->authorize('update', $article);

        $categories = KbCategory::orderBy('position')->get();

        return view('kb.article-form', compact('article', 'categories'));
    }

    public function update(Request $request, KbArticle $article)
    {
        $this->authorize('update', $article);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'kb_category_id' => 'required|exists:kb_categories,id',
            'priority' => 'nullable|integer|min:0|max:100',
            'change_summary' => 'nullable|string|max:500',
            'visible_on_webchat' => 'nullable|boolean',
            'visible_on_whatsapp' => 'nullable|boolean',
            'visible_on_instagram' => 'nullable|boolean',
            'visible_on_facebook' => 'nullable|boolean',
        ]);

        $data['visible_on_webchat'] = $request->has('visible_on_webchat');
        $data['visible_on_whatsapp'] = $request->has('visible_on_whatsapp');
        $data['visible_on_instagram'] = $request->has('visible_on_instagram');
        $data['visible_on_facebook'] = $request->has('visible_on_facebook');

        $this->kbService->updateArticle($article, $data, $request->user());

        return redirect()->route('kb.articles.show', $article)->with('success', 'Article updated.');
    }

    public function publish(KbArticle $article, Request $request)
    {
        $this->authorize('publish', $article);

        $this->kbService->publishArticle($article, $request->user());

        return redirect()->route('kb.articles.show', $article)->with('success', 'Article published.');
    }

    public function archive(KbArticle $article, Request $request)
    {
        $this->authorize('update', $article);

        $this->kbService->archiveArticle($article, $request->user());

        return redirect()->route('kb.articles.show', $article)->with('success', 'Article archived.');
    }

    public function destroy(KbArticle $article)
    {
        $this->authorize('delete', $article);

        $this->kbService->deleteArticle($article);

        return redirect()->route('kb.articles')->with('success', 'Article deleted.');
    }
}

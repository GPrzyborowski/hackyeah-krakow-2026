<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewArticleRequest;
use App\Http\Requests\Admin\SaveArticleRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    private const int WORDS_PER_MINUTE = 200;

    /**
     * Every article, newest first, with its publication state.
     */
    public function index(): Response
    {
        $articles = Article::query()
            ->select(['id', 'title', 'slug', 'category', 'reading_minutes', 'is_featured', 'published_at'])
            ->orderByRaw('published_at is null desc')
            ->latest('published_at')
            ->latest('id')
            ->get();

        return Inertia::render('admin/articles/Index', [
            'articles' => $articles->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category_label' => $article->category->label(),
                'reading_minutes' => $article->reading_minutes,
                'is_featured' => $article->is_featured,
                'status' => $this->status($article),
                'published_at' => $article->published_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm(null);
    }

    public function store(SaveArticleRequest $request): RedirectResponse
    {
        $this->persist(new Article, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artykuł zapisany.']);

        return to_route('admin.articles.index');
    }

    public function edit(Article $article): Response
    {
        return $this->renderForm($article);
    }

    public function update(SaveArticleRequest $request, Article $article): RedirectResponse
    {
        $this->persist($article, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zmiany zapisane.']);

        return to_route('admin.articles.index');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artykuł usunięty.']);

        return to_route('admin.articles.index');
    }

    /**
     * Render Markdown exactly like the public blog does (raw HTML stripped, unsafe links disabled).
     */
    public function preview(PreviewArticleRequest $request): JsonResponse
    {
        return response()->json([
            'html' => Str::markdown((string) $request->validated('body'), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }

    private function renderForm(?Article $article): Response
    {
        return Inertia::render('admin/articles/Form', [
            'article' => $article ? [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category->value,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'reading_minutes' => $article->reading_minutes,
                'is_featured' => $article->is_featured,
                'published_at' => $article->published_at?->toIso8601String(),
            ] : null,
            'categories' => collect(ArticleCategory::cases())->map(fn (ArticleCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ]),
            'wordsPerMinute' => self::WORDS_PER_MINUTE,
        ]);
    }

    /**
     * Save the article; only one article can be featured at a time.
     */
    private function persist(Article $article, SaveArticleRequest $request): void
    {
        $data = $request->validated();

        $article->fill([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'category' => $data['category'],
            'excerpt' => $data['excerpt'],
            'body' => $data['body'],
            'reading_minutes' => $data['reading_minutes'] ?? $this->estimateReadingMinutes($data['body']),
            'is_featured' => $data['is_featured'],
            'published_at' => $data['published_at'] !== null
                ? Date::parse($data['published_at'])->setTimezone(config('app.timezone'))
                : null,
        ]);

        DB::transaction(function () use ($article): void {
            if ($article->is_featured) {
                Article::query()
                    ->where('is_featured', true)
                    ->when($article->exists, fn ($query) => $query->whereKeyNot($article->id))
                    ->update(['is_featured' => false]);
            }

            $article->save();
        });
    }

    /**
     * Words are whitespace-separated tokens with at least one letter or digit, so Markdown markers are not counted.
     * The article form uses the same heuristic to show the estimate live.
     */
    private function estimateReadingMinutes(string $markdown): int
    {
        $words = (int) preg_match_all('/\S*[\p{L}\p{N}]\S*/u', $markdown);

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /**
     * @return 'draft'|'scheduled'|'published'
     */
    private function status(Article $article): string
    {
        return match (true) {
            $article->published_at === null => 'draft',
            $article->published_at->isFuture() => 'scheduled',
            default => 'published',
        };
    }
}

<?php

namespace App\Services\Ai;

use App\Models\Article;
use App\Models\LegalSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Answers labour-law questions from retrieved legal sources and blog articles (Claude, or an extractive fallback).
 */
class LegalAssistant
{
    public const string DISCLAIMER = 'To informacja ogólna, a nie porada prawna. Przy sporze skontaktuj się z prawnikiem.';

    private const string SYSTEM_PROMPT = <<<'PROMPT'
        Jesteś asystentem MomJobs - serwisu pracy dla kobiet w ciąży i mam wracających do pracy.
        Odpowiadasz po polsku, krótko (2-5 zdań), ciepło i konkretnie, zwracając się do użytkowniczki na "Ty".
        Odpowiadaj WYŁĄCZNIE na podstawie źródeł przekazanych w wiadomości. Nie dopowiadaj przepisów, kwot ani
        terminów, których w źródłach nie ma. Jeśli źródła nie odpowiadają na pytanie, napisz wprost, że nie masz
        na ten temat źródła, i zaproponuj kontakt z prawnikiem lub ZUS/PIP.
        Nie udzielaj porad prawnych w konkretnym sporze - podawaj informację ogólną.
        W polu citations podaj identyfikatory (np. "L3", "A5") tylko tych źródeł, z których faktycznie korzystasz.
        Nie wstawiaj identyfikatorów źródeł do treści odpowiedzi.
        PROMPT;

    public function __construct(
        private readonly KnowledgeRetriever $retriever,
        private readonly ClaudeClient $claude,
    ) {}

    public function answer(string $question): AssistantAnswer
    {
        $legalSources = $this->retriever->legalSources($question);
        $articles = $this->retriever->articles($question);

        if ($legalSources->isEmpty() && $articles->isEmpty()) {
            return new AssistantAnswer(
                'Nie mam jeszcze źródła, które odpowiada na to pytanie. Spróbuj zapytać inaczej, np. o urlop rodzicielski, '
                .'zasiłek macierzyński albo rozmowę rekrutacyjną w ciąży. W konkretnej sprawie najlepiej skontaktuj się z prawnikiem, ZUS lub PIP.',
            );
        }

        if ($this->claude->isConfigured()) {
            try {
                return $this->answerWithClaude($question, $legalSources, $articles);
            } catch (Throwable $exception) {
                Log::warning('Claude legal assistant failed, using extractive fallback.', ['error' => $exception->getMessage()]);
            }
        }

        return $this->fallbackAnswer($legalSources, $articles);
    }

    /**
     * @param  Collection<int, LegalSource>  $legalSources
     * @param  Collection<int, Article>  $articles
     */
    private function answerWithClaude(string $question, Collection $legalSources, Collection $articles): AssistantAnswer
    {
        $sources = $this->numberedSources($legalSources, $articles);

        $sourcesText = collect($sources)
            ->map(fn (array $source, string $id): string => "<source id=\"{$id}\" label=\"{$source['label']}\">\n{$source['text']}\n</source>")
            ->implode("\n\n");

        $result = $this->claude->json(
            self::SYSTEM_PROMPT,
            [['type' => 'text', 'text' => "Źródła:\n{$sourcesText}\n\nPytanie użytkowniczki:\n<question>\n{$question}\n</question>"]],
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['answer', 'citations'],
                'properties' => [
                    'answer' => ['type' => 'string'],
                    'citations' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            2000,
        );

        $answer = trim((string) ($result['answer'] ?? ''));

        if ($answer === '') {
            throw new ClaudeException('Claude returned an empty answer.');
        }

        $citations = collect($result['citations'] ?? [])
            ->filter(fn (mixed $id): bool => is_string($id) && isset($sources[$id]))
            ->unique()
            ->map(fn (string $id): array => $sources[$id]['citation'])
            ->values()
            ->all();

        return new AssistantAnswer($answer, $citations);
    }

    /**
     * @param  Collection<int, LegalSource>  $legalSources
     * @param  Collection<int, Article>  $articles
     */
    private function fallbackAnswer(Collection $legalSources, Collection $articles): AssistantAnswer
    {
        $citations = [];
        $paragraphs = [];

        /** @var LegalSource|null $bestSource */
        $bestSource = $legalSources->first();

        if ($bestSource) {
            $paragraphs[] = "Najbliżej Twojego pytania jest ten przepis ({$bestSource->label()} – {$bestSource->title}):";
            $paragraphs[] = $bestSource->content;
            $citations[] = $this->legalCitation($bestSource);

            /** @var LegalSource|null $secondSource */
            $secondSource = $legalSources->get(1);

            if ($secondSource) {
                $paragraphs[] = "Zajrzyj też do: {$secondSource->label()} ({$secondSource->title}).";
                $citations[] = $this->legalCitation($secondSource);
            }
        }

        /** @var Article|null $article */
        $article = $articles->first();

        if ($article) {
            $paragraphs[] = $bestSource
                ? "Więcej praktycznych wskazówek znajdziesz w tekście „{$article->title}”."
                : "Na ten temat piszemy w tekście „{$article->title}”: {$article->excerpt}";
            $citations[] = $this->articleCitation($article);
        }

        return new AssistantAnswer(implode("\n\n", $paragraphs), $citations);
    }

    /**
     * @param  Collection<int, LegalSource>  $legalSources
     * @param  Collection<int, Article>  $articles
     * @return array<string, array{label: string, text: string, citation: array{type: 'legal'|'article', label: string, url: string|null}}>
     */
    private function numberedSources(Collection $legalSources, Collection $articles): array
    {
        $sources = [];

        foreach ($legalSources as $source) {
            $sources['L'.$source->id] = [
                'label' => $source->label(),
                'text' => $source->title."\n".$source->content,
                'citation' => $this->legalCitation($source),
            ];
        }

        foreach ($articles as $article) {
            $sources['A'.$article->id] = [
                'label' => 'Blog: '.$article->title,
                'text' => $article->excerpt."\n".Str::limit($article->body, 4000),
                'citation' => $this->articleCitation($article),
            ];
        }

        return $sources;
    }

    /**
     * @return array{type: 'legal', label: string, url: null}
     */
    private function legalCitation(LegalSource $source): array
    {
        return ['type' => 'legal', 'label' => $source->label(), 'url' => null];
    }

    /**
     * @return array{type: 'article', label: string, url: string}
     */
    private function articleCitation(Article $article): array
    {
        return ['type' => 'article', 'label' => 'Blog: '.$article->title, 'url' => route('blog.show', $article, false)];
    }
}

<?php
declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Article;
use App\Domain\Lawyer\Entity\LawyerProfile;
use OpenSearch\Client;

/**
 * Индексация сущностей в OpenSearch (ТЗ SRCH-01/02).
 * Индексы: lex_lawyers, lex_questions, lex_articles.
 */
final readonly class SearchIndexer
{
    public const IDX_LAWYERS = 'lex_lawyers';
    public const IDX_QUESTIONS = 'lex_questions';
    public const IDX_ARTICLES = 'lex_articles';

    public function __construct(private Client $client) {}

    public function createIndices(): void
    {
        $analyzer = [
            'settings' => [
                'analysis' => [
                    'analyzer' => [
                        'lex_text' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['lowercase']],
                    ],
                ],
            ],
        ];

        foreach ([
            self::IDX_LAWYERS => ['name' => 't', 'bio' => 't', 'city' => 'k', 'specializations' => 'k', 'rating' => 'f', 'is_featured' => 'b', 'is_online' => 'b', 'slug' => 'k'],
            self::IDX_QUESTIONS => ['title' => 't', 'body' => 't', 'category' => 'k', 'status' => 'k', 'created_at' => 'd'],
            self::IDX_ARTICLES => ['title' => 't', 'body' => 't', 'excerpt' => 't', 'type' => 'k', 'slug' => 'k', 'published_at' => 'd'],
        ] as $index => $fields) {
            if ($this->client->indices()->exists(['index' => $index])) {
                continue;
            }
            $props = [];
            foreach ($fields as $field => $t) {
                $props[$field] = match ($t) {
                    't' => ['type' => 'text', 'analyzer' => 'lex_text'],
                    'k' => ['type' => 'keyword'],
                    'f' => ['type' => 'float'],
                    'b' => ['type' => 'boolean'],
                    'd' => ['type' => 'date'],
                };
            }
            $this->client->indices()->create([
                'index' => $index,
                'body' => $analyzer + ['mappings' => ['properties' => $props]],
            ]);
        }
    }

    public function indexLawyer(LawyerProfile $l): void
    {
        $this->client->index([
            'index' => self::IDX_LAWYERS,
            'id' => $l->getId()->toRfc4122(),
            'body' => [
                'name' => $l->getUser()->getFullName(),
                'bio' => $l->getBio(),
                'city' => $l->getCity(),
                'slug' => $l->getSlug(),
                'specializations' => $l->getSpecializations()->map(fn ($s) => $s->getSlug())->toArray(),
                'rating' => $l->getRating(),
                'is_featured' => $l->isFeatured(),
                'is_online' => $l->isOnline(),
            ],
        ]);
    }

    public function indexQuestion(Question $q): void
    {
        $this->client->index([
            'index' => self::IDX_QUESTIONS,
            'id' => $q->getId()->toRfc4122(),
            'body' => [
                'title' => $q->getTitle(),
                'body' => $q->getBody(),
                'category' => $q->getCategory()->getSlug(),
                'status' => $q->getStatus(),
                'created_at' => $q->getCreatedAt()->format(DATE_ATOM),
            ],
        ]);
    }

    public function indexArticle(Article $a): void
    {
        $this->client->index([
            'index' => self::IDX_ARTICLES,
            'id' => $a->getId()->toRfc4122(),
            'body' => [
                'title' => $a->getTitle(),
                'body' => strip_tags($a->getBody()),
                'excerpt' => $a->getExcerpt(),
                'type' => $a->getType(),
                'slug' => $a->getSlug(),
                'published_at' => $a->getPublishedAt()?->format(DATE_ATOM),
            ],
        ]);
    }

    /** Глобальный поиск по всем индексам (SRCH-01). */
    public function search(string $query, string $type = 'all', int $page = 1, int $perPage = 20): array
    {
        $indices = match ($type) {
            'lawyers' => self::IDX_LAWYERS,
            'questions' => self::IDX_QUESTIONS,
            'articles' => self::IDX_ARTICLES,
            default => implode(',', [self::IDX_LAWYERS, self::IDX_QUESTIONS, self::IDX_ARTICLES]),
        };

        $result = $this->client->search([
            'index' => $indices,
            'body' => [
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['title^3', 'name^3', 'excerpt^2', 'bio', 'body'],
                        'fuzziness' => 'AUTO', // SRCH-04
                    ],
                ],
            ],
        ]);

        return [
            'total' => $result['hits']['total']['value'] ?? 0,
            'hits' => array_map(
                fn (array $hit) => ['index' => $hit['_index'], 'id' => $hit['_id'], 'score' => $hit['_score']] + $hit['_source'],
                $result['hits']['hits'] ?? []
            ),
        ];
    }
}

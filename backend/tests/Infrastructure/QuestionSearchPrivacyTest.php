<?php
declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Category;
use App\Domain\Identity\Entity\User;
use App\Infrastructure\Search\SearchIndexer;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;

final class QuestionSearchPrivacyTest extends TestCase
{
    public function testExistingQuestionIndexReceivesVisibilityMapping(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('exists')->willReturn(true);
        $indices->expects(self::never())->method('create');
        $indices->expects(self::once())->method('putMapping')->with([
            'index' => SearchIndexer::IDX_QUESTIONS,
            'body' => ['properties' => ['type' => ['type' => 'keyword']]],
        ])->willReturn([]);
        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);
        (new SearchIndexer($client))->createIndices();
    }

    public function testRestrictedQuestionsDeleteStaleCopiesInsteadOfIndexing(): void
    {
        foreach (['private', 'paid', 'moderation', 'archived'] as $kind) {
            $q = $this->question($kind);
            $client = $this->createMock(Client::class);
            $client->expects(self::never())->method('index');
            $client->expects(self::once())->method('delete')->with(self::callback(fn (array $p) =>
                $p['index'] === SearchIndexer::IDX_QUESTIONS && $p['id'] === $q->getId()->toRfc4122()
                && $p['client']['ignore'] === [404] && $p['refresh'] === 'wait_for'
            ))->willReturn([]);
            (new SearchIndexer($client))->indexQuestion($q);
        }
    }

    public function testPublicQuestionIncludesExplicitVisibilityMetadata(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('index')->with(self::callback(fn (array $p) =>
            $p['body']['type'] === 'public' && $p['body']['status'] === 'open'
        ))->willReturn([]);
        (new SearchIndexer($client))->indexQuestion($this->question('public'));
    }

    public function testSearchAlwaysFiltersQuestionVisibilityIncludingGlobalSearch(): void
    {
        foreach (['questions', 'all', 'unknown'] as $type) {
            $client = $this->createMock(Client::class);
            $client->expects(self::once())->method('search')->with(self::callback(function (array $p): bool {
                $filter = $p['body']['query']['bool']['filter'][0]['bool'];
                self::assertSame(1, $filter['minimum_should_match']);
                self::assertSame(['term' => ['_index' => SearchIndexer::IDX_QUESTIONS]], $filter['should'][0]['bool']['must_not'][0]);
                self::assertSame([
                    ['term' => ['type' => 'public']],
                    ['terms' => ['status' => ['open', 'answered', 'closed']]],
                ], $filter['should'][1]['bool']['filter']);
                return true;
            }))->willReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);
            self::assertSame([], (new SearchIndexer($client))->search('secret', $type)['hits']);
        }
    }

    public function testPurgeExcludesOnlyExplicitlyPublicVisibleDocuments(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('deleteByQuery')->with(self::callback(function (array $p): bool {
            self::assertSame(SearchIndexer::IDX_QUESTIONS, $p['index']);
            self::assertTrue($p['refresh']);
            self::assertSame([
                ['term' => ['type' => 'public']],
                ['terms' => ['status' => ['open', 'answered', 'closed']]],
            ], $p['body']['query']['bool']['must_not'][0]['bool']['filter']);
            return true;
        }))->willReturn([]);
        (new SearchIndexer($client))->purgeNonPublicQuestions();
    }

    public function testInvalidQuestionTypeIsRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->question('invalid');
    }

    private function question(string $kind): Question
    {
        $q = new Question(new User('owner@example.test', 'Owner'), 'Test question', 'Secret marker',
            new Category('Test', 'test'), in_array($kind, ['moderation', 'archived'], true) ? 'public' : $kind);
        if (in_array($kind, ['moderation', 'archived'], true)) {
            (new \ReflectionProperty(Question::class, 'status'))->setValue($q, $kind);
        }
        return $q;
    }
}

<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Category;
use App\Domain\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class QuestionPrivacyTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $directory;
    private User $owner;
    private User $stranger;
    private User $lawyer;
    /** @var array<string, Question> */
    private array $questions;
    /** @var array<string, mixed> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/lex-privacy-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents($this->directory . '/private.pem', $private);
        file_put_contents($this->directory . '/public.pem', openssl_pkey_get_details($key)['key']);
        foreach ([
            'DATABASE_URL' => 'sqlite:///' . $this->directory . '/test.db',
            'JWT_SECRET_KEY' => $this->directory . '/private.pem',
            'JWT_PUBLIC_KEY' => $this->directory . '/public.pem',
            'JWT_PASSPHRASE' => '',
        ] as $name => $value) {
            $this->previousEnv[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->owner = new User('owner@example.test', 'Owner');
        $this->stranger = new User('stranger@example.test', 'Stranger');
        $this->lawyer = new User('lawyer@example.test', 'Lawyer', User::ROLE_LAWYER);
        $category = new Category('Test', 'test');
        foreach ([$this->owner, $this->stranger, $this->lawyer, $category] as $entity) {
            $em->persist($entity);
        }
        $this->questions = [];
        foreach (['public', 'private', 'paid', 'moderation', 'archived'] as $kind) {
            $type = in_array($kind, ['private', 'paid'], true) ? $kind : 'public';
            $q = new Question($this->owner, 'Question ' . $kind, 'Secret marker ' . $kind, $category, $type);
            if (in_array($kind, ['moderation', 'archived'], true)) {
                (new \ReflectionProperty(Question::class, 'status'))->setValue($q, $kind);
            }
            $this->questions[$kind] = $q;
            $em->persist($q);
        }
        $em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->previousEnv as $name => [$env, $server]) {
            if ($env === null) { unset($_ENV[$name]); } else { $_ENV[$name] = $env; }
            if ($server === null) { unset($_SERVER[$name]); } else { $_SERVER[$name] = $server; }
        }
        foreach (glob($this->directory . '/*') as $file) { unlink($file); }
        rmdir($this->directory);
    }

    public function testAnonymousAndStrangersCannotReadRestrictedQuestions(): void
    {
        foreach ([null, $this->stranger, $this->lawyer] as $viewer) {
            foreach (['private', 'paid', 'moderation', 'archived'] as $kind) {
                $this->request('GET', $kind, $viewer);
                self::assertResponseStatusCodeSame(404);
                self::assertStringNotContainsString('Secret marker', $this->client->getResponse()->getContent());
            }
        }
    }

    public function testOwnerCanReadRestrictedQuestionsAndPublicQuestionIsPublic(): void
    {
        foreach (['private', 'paid', 'moderation', 'archived'] as $kind) {
            $this->request('GET', $kind, $this->owner);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Secret marker ' . $kind, $this->client->getResponse()->getContent());
        }
        $this->request('GET', 'public', null);
        self::assertResponseIsSuccessful();
    }

    public function testPublicListExcludesRestrictedQuestionsEvenWithStatusFilter(): void
    {
        foreach (['', '?status=moderation', '?status=archived'] as $query) {
            $this->client->request('GET', '/api/v1/questions' . $query);
            self::assertResponseIsSuccessful();
            $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertCount($query === '' ? 1 : 0, $payload['data']);
        }
    }

    public function testLawyerCannotAnswerPrivatePaidOrHiddenQuestion(): void
    {
        foreach (['private', 'paid', 'moderation', 'archived'] as $kind) {
            $this->request('POST', $kind, $this->lawyer, '/answers');
            self::assertResponseStatusCodeSame(404);
        }
        $this->request('POST', 'public', $this->lawyer, '/answers');
        self::assertResponseStatusCodeSame(201);
    }

    private function request(string $method, string $kind, ?User $viewer, string $suffix = ''): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($viewer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . static::getContainer()->get(JWTTokenManagerInterface::class)->create($viewer);
        }
        $this->client->request($method, '/api/v1/questions/' . $this->questions[$kind]->getId()->toRfc4122() . $suffix,
            server: $server, content: json_encode(['body' => 'A sufficiently detailed lawyer response.']));
    }
}

<?php
declare(strict_types=1);

namespace App\Infrastructure\Search;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OpenSearchClientFactory
{
    public function __construct(
        #[Autowire('%app.opensearch_url%')] private string $url,
    ) {}

    public function create(): Client
    {
        return (new ClientBuilder())
            ->setHosts([$this->url])
            ->setSSLVerification(false)
            ->build();
    }
}

<?php
declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Сховище файлів: MinIO (dev/staging) / AWS S3 (prod). Path-style для сумісності з MinIO.
 *
 * Розділення endpoint:
 *  - $endpoint (S3_ENDPOINT) — внутрішня адреса для завантаження/підпису (http://minio:9000).
 *  - $publicUrl (S3_PUBLIC_URL) — публічна адреса для формування посилань, доступних із браузера
 *    (напр. https://домен/media). Якщо не задано — використовується $endpoint.
 */
final class S3Storage
{
    private ?S3Client $client = null;

    public function __construct(
        #[Autowire('%env(S3_ENDPOINT)%')] private readonly string $endpoint,
        #[Autowire('%env(S3_KEY)%')] private readonly string $key,
        #[Autowire('%env(S3_SECRET)%')] private readonly string $secret,
        #[Autowire('%env(S3_BUCKET)%')] private readonly string $bucket,
        #[Autowire('%env(default::S3_PUBLIC_URL)%')] private readonly ?string $publicUrl = null,
    ) {}

    private function client(): S3Client
    {
        return $this->client ??= new S3Client([
            'version' => 'latest',
            'region' => 'eu-central-1',
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $this->key, 'secret' => $this->secret],
        ]);
    }

    /** @return string публічний URL об'єкта (через S3_PUBLIC_URL, якщо задано) */
    public function put(string $objectKey, string $contents, string $mimeType, bool $private = true): string
    {
        $this->putObject($objectKey, $contents, $mimeType, $private);

        $base = $this->publicUrl !== null && $this->publicUrl !== ''
            ? rtrim($this->publicUrl, '/')                              // https://домен/media
            : rtrim($this->endpoint, '/') . '/' . $this->bucket;        // http://minio:9000/lex-media
        return $base . '/' . ltrim($objectKey, '/');
    }

    private function putObject(string $objectKey, string $contents, string $mimeType, bool $private): void
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
            'Body' => $contents,
            'ContentType' => $mimeType,
        ];
        // ACL на MinIO з дефолтною політикою може відхилятись — публічність
        // забезпечує bucket policy (avatars/ відкрито через minio-init), тож ACL не критичний.
        if ($private) {
            $params['ACL'] = 'private';
        }
        $this->client()->putObject($params);
    }

    /** Presigned URL для перегляду приватного документа адміном (15 хв). */
    public function presignedGet(string $objectKey, int $ttlMinutes = 15): string
    {
        $cmd = $this->client()->getCommand('GetObject', ['Bucket' => $this->bucket, 'Key' => $objectKey]);
        $uri = (string) $this->client()->createPresignedRequest($cmd, "+{$ttlMinutes} minutes")->getUri();
        // Підмінюємо внутрішній host на публічний, щоб посилання відкривалось із браузера
        if ($this->publicUrl !== null && $this->publicUrl !== '') {
            $internal = rtrim($this->endpoint, '/') . '/' . $this->bucket;
            $public = rtrim($this->publicUrl, '/');
            $uri = str_replace($internal, $public, $uri);
        }
        return $uri;
    }

}

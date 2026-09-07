<?php
declare(strict_types=1);

namespace App\Infrastructure\Payment;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Клиент LiqPay API v3 (checkout + server-server callback).
 * Протокол: data = base64(json), signature = base64(sha1(private + data + private, raw)).
 * Docs: https://www.liqpay.ua/documentation/api/aquiring/checkout/doc
 *
 * ВАЖНО: callback подписывается приватным ключом на стороне LiqPay тем же алгоритмом,
 * поэтому верификация — пересчёт подписи и сравнение через hash_equals (timing-safe).
 */
final readonly class LiqPayClient
{
    public const CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';
    public const API_VERSION = 3;

    public function __construct(
        #[Autowire('%env(LIQPAY_PUBLIC_KEY)%')] private string $publicKey,
        #[Autowire('%env(LIQPAY_PRIVATE_KEY)%')] private string $privateKey,
        #[Autowire('%env(APP_PUBLIC_URL)%')] private string $appPublicUrl,
    ) {}

    /**
     * Параметры для HTML-формы/redirect на checkout.
     * @return array{checkout_url:string, data:string, signature:string}
     */
    public function buildCheckout(
        string $orderId,
        string $amount,
        string $currency,
        string $description,
        string $resultPath = '/payments/result',
    ): array {
        $payload = [
            'version' => self::API_VERSION,
            'public_key' => $this->publicKey,
            'action' => 'pay',
            'amount' => (float) $amount,
            'currency' => $currency,
            'description' => $description,
            'order_id' => $orderId,
            'language' => 'uk',
            'result_url' => rtrim($this->appPublicUrl, '/') . $resultPath . '?order_id=' . urlencode($orderId),
            'server_url' => rtrim($this->appPublicUrl, '/') . '/api/v1/payments/webhook/liqpay',
        ];

        $data = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return [
            'checkout_url' => self::CHECKOUT_URL,
            'data' => $data,
            'signature' => $this->sign($data),
        ];
    }

    public function sign(string $data): string
    {
        return base64_encode(sha1($this->privateKey . $data . $this->privateKey, true));
    }

    /** Timing-safe проверка подписи callback (защита от подделки webhook). */
    public function verifyCallback(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }

    /** @return array<string,mixed> декодированный payload callback */
    public function decodeCallback(string $data): array
    {
        $json = base64_decode($data, true);
        if ($json === false) {
            throw new \InvalidArgumentException('Некорректный base64 в callback LiqPay.');
        }
        return (array) json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }

    /**
     * Статусы LiqPay → внутренние. success/subscribed = оплачен;
     * sandbox — успех в тестовом режиме; failure/error/reversed = неуспех.
     */
    public function isSuccessStatus(string $liqpayStatus): bool
    {
        return in_array($liqpayStatus, ['success', 'subscribed', 'sandbox'], true);
    }

    public function isFailureStatus(string $liqpayStatus): bool
    {
        return in_array($liqpayStatus, ['failure', 'error', 'reversed', 'expired'], true);
    }
}

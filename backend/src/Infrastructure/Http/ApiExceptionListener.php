<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Єдиний обробник помилок для /api: повертає RFC 7807 (application/problem+json)
 * замість дефолтної HTML-сторінки Symfony.
 *
 * Якщо S3_DEBUG_ERRORS=1 (лише staging/дебаг) — додає клас, повідомлення, файл і рядок
 * винятку прямо у відповідь, щоб швидко діагностувати 500 без доступу до логів.
 * У проді цей прапорець вимкнено, деталі приховані.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%env(bool:APP_DEBUG_ERRORS)%')] private bool $exposeDetails,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return; // не чіпаємо не-API маршрути
        }

        $e = $event->getThrowable();
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;

        // 5xx логуємо як критичні
        if ($status >= 500) {
            $this->logger->critical('API exception: ' . $e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $request->getPathInfo(),
            ]);
        }

        $payload = [
            'type' => 'about:blank',
            'title' => $status >= 500 ? 'Внутрішня помилка сервера' : 'Помилка запиту',
            'status' => $status,
            'detail' => $status >= 500 && !$this->exposeDetails
                ? 'Сталася непередбачена помилка. Спробуйте пізніше.'
                : $e->getMessage(),
        ];

        if ($this->exposeDetails && $status >= 500) {
            $payload['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ];
        }

        $event->setResponse(new JsonResponse($payload, $status, ['Content-Type' => 'application/problem+json']));
    }
}

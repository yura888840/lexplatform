<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Application\Identity\Command\RegisterUser\RegisterUserCommand;
use App\Application\Identity\Command\RegisterUser\RegisterUserHandler;
use App\Domain\Identity\Entity\RefreshToken;
use App\Domain\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth')]
final class AuthController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request, RegisterUserHandler $handler): JsonResponse
    {
        $data = $this->jsonBody($request);
        try {
            $user = $handler(new RegisterUserCommand(
                email: (string) ($data['email'] ?? ''),
                password: (string) ($data['password'] ?? ''),
                fullName: (string) ($data['full_name'] ?? ''),
                role: (string) ($data['role'] ?? 'client'),
                acceptsPersonalDataProcessing: (bool) ($data['accepts_personal_data'] ?? false),
                ip: $request->getClientIp(),
            ));
        } catch (\DomainException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->tokenPayload($user), Response::HTTP_CREATED);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $this->jsonBody($request);
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => mb_strtolower(trim((string) ($data['email'] ?? '')))]);

        if (!$user || $user->isDeleted() || !$this->hasher->isPasswordValid($user, (string) ($data['password'] ?? ''))) {
            return $this->problem('Неверный email или пароль.', Response::HTTP_UNAUTHORIZED);
        }
        if ($user->getStatus() === User::STATUS_BANNED) {
            return $this->problem('Аккаунт заблокирован.', Response::HTTP_FORBIDDEN);
        }

        $user->recordLogin();
        $this->em->flush();

        return $this->json($this->tokenPayload($user));
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $plain = (string) ($this->jsonBody($request)['refresh_token'] ?? '');
        $token = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['tokenHash' => RefreshToken::hash($plain)]);

        if (!$token || $token->isExpired()) {
            return $this->problem('Refresh-токен недействителен или истёк.', Response::HTTP_UNAUTHORIZED);
        }

        $user = $token->getUser();
        $this->em->remove($token); // ротация refresh-токенов
        $payload = $this->tokenPayload($user);
        $this->em->flush();

        return $this->json($payload);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $plain = (string) ($this->jsonBody($request)['refresh_token'] ?? '');
        $token = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['tokenHash' => RefreshToken::hash($plain)]);
        if ($token) {
            $this->em->remove($token);
            $this->em->flush();
        }
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'full_name' => $user->getFullName(),
            'role' => $user->getRole(),
            'locale' => $user->getLocale(),
            'avatar_url' => $user->getAvatarUrl(),
        ]);
    }

    /** access 15 мин + refresh 30 дней (ТЗ AUTH-05). */
    private function tokenPayload(User $user): array
    {
        $plainRefresh = bin2hex(random_bytes(32));
        $this->em->persist(new RefreshToken($user, $plainRefresh));
        $this->em->flush();

        return [
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'full_name' => $user->getFullName(),
                'role' => $user->getRole(),
            ],
            'access_token' => $this->jwtManager->create($user),
            'refresh_token' => $plainRefresh,
        ];
    }
}

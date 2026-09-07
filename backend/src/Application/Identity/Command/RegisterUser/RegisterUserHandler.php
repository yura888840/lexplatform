<?php
declare(strict_types=1);

namespace App\Application\Identity\Command\RegisterUser;

use App\Domain\Identity\Entity\User;
use App\Domain\Identity\Entity\UserConsent;
use App\Domain\Identity\Event\UserRegistered;
use App\Domain\Identity\Exception\UserAlreadyExistsException;
use App\Domain\Identity\ValueObject\Email;
use App\Domain\Lawyer\Entity\LawyerProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class RegisterUserHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(RegisterUserCommand $command): User
    {
        $email = new Email($command->email);

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email->value])) {
            throw UserAlreadyExistsException::withEmail($email->value);
        }
        if (strlen($command->password) < 8) {
            throw new \DomainException('Пароль должен содержать минимум 8 символов.');
        }
        $role = in_array($command->role, [User::ROLE_CLIENT, User::ROLE_LAWYER], true)
            ? $command->role : User::ROLE_CLIENT;

        // Юрист публикует персональные данные в каталоге → обязательное согласие
        if ($role === User::ROLE_LAWYER && !$command->acceptsPersonalDataProcessing) {
            throw new \DomainException('Для реєстрації юриста необхідна згода на обробку та розповсюдження персональних даних.');
        }

        $user = new User($email->value, $command->fullName, $role);
        $user->setPasswordHash($this->hasher->hashPassword($user, $command->password));
        $this->em->persist($user);

        // Юрист сразу получает профиль (заполняет позже, PROF-02)
        if ($role === User::ROLE_LAWYER) {
            $slug = strtolower((new AsciiSlugger('uk'))->slug($command->fullName)->toString())
                . '-' . substr($user->getId()->toRfc4122(), 0, 8);
            $this->em->persist(new LawyerProfile($user, $slug));
        }

        if ($command->acceptsPersonalDataProcessing) {
            $this->em->persist(new UserConsent(
                $user, UserConsent::TYPE_PERSONAL_DATA, UserConsent::CURRENT_VERSION_PERSONAL_DATA, $command->ip
            ));
        }

        $this->em->flush();

        $this->bus->dispatch(new UserRegistered($user->getId(), $user->getEmail(), $user->getFullName()));

        return $user;
    }
}

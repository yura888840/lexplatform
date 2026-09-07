<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Entity\Review;
use PHPUnit\Framework\TestCase;

final class ReviewTest extends TestCase
{
    public function testApprovedReviewRecalculatesRating(): void
    {
        $lawyerUser = new User('l@test.local', 'Юрист', User::ROLE_LAWYER);
        $client = new User('c@test.local', 'Клиент', User::ROLE_CLIENT);
        $profile = new LawyerProfile($lawyerUser, 'test-lawyer');

        (new Review($profile, $client, 5, null))->approve();
        (new Review($profile, $client, 4, null))->approve();

        self::assertSame(2, $profile->getReviewsCount());
        self::assertSame(4.5, $profile->getRating());
    }

    public function testInvalidRatingRejected(): void
    {
        $profile = new LawyerProfile(new User('l@test.local', 'Юрист', User::ROLE_LAWYER), 'x');
        $this->expectException(\DomainException::class);
        new Review($profile, new User('c@test.local', 'К', User::ROLE_CLIENT), 6, null);
    }
}

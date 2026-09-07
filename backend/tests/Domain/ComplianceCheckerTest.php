<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Service\ComplianceChecker;
use PHPUnit\Framework\TestCase;

final class ComplianceCheckerTest extends TestCase
{
    private function lawyer(): LawyerProfile
    {
        return new LawyerProfile(new User('l@test.local', 'Юрист', User::ROLE_LAWYER), 'test');
    }

    public function testPaidModeWithSubscriptionIsOk(): void
    {
        $l = $this->lawyer();
        $l->switchContributionMode(LawyerProfile::MODE_PAID);
        self::assertSame(LawyerProfile::COMPLIANCE_OK, (new ComplianceChecker())->evaluate($l, true, 0));
    }

    public function testPaidModeWithoutSubscriptionNonCompliant(): void
    {
        $l = $this->lawyer();
        $l->switchContributionMode(LawyerProfile::MODE_PAID);
        self::assertSame(LawyerProfile::COMPLIANCE_NON_COMPLIANT, (new ComplianceChecker())->evaluate($l, false, 0));
    }

    public function testNewLawyerInOnboardingGrace(): void
    {
        // 0 статей, регистрация сегодня → onboarding (14 дней грейса)
        self::assertSame(LawyerProfile::COMPLIANCE_ONBOARDING, (new ComplianceChecker())->evaluate($this->lawyer(), false, 0));
    }

    public function testNoArticlesAfterGraceNonCompliant(): void
    {
        $l = $this->lawyer();
        $now = new \DateTimeImmutable('+20 days'); // 20 дней после регистрации, статей нет
        self::assertSame(LawyerProfile::COMPLIANCE_NON_COMPLIANT, (new ComplianceChecker())->evaluate($l, false, 0, $now));
    }

    public function testFreshArticleIsOk(): void
    {
        $l = $this->lawyer();
        $l->registerPublishedArticle(); // last_article_at = сейчас
        self::assertSame(LawyerProfile::COMPLIANCE_OK, (new ComplianceChecker())->evaluate($l, false, 1));
    }

    public function testStaleArticleWarnsThenBlocks(): void
    {
        $l = $this->lawyer();
        $l->registerPublishedArticle();
        $checker = new ComplianceChecker();

        // 5 дней без статьи → warning (цель — через день)
        self::assertSame(LawyerProfile::COMPLIANCE_WARNING, $checker->evaluate($l, false, 1, new \DateTimeImmutable('+5 days')));
        // 10 дней → non_compliant
        self::assertSame(LawyerProfile::COMPLIANCE_NON_COMPLIANT, $checker->evaluate($l, false, 1, new \DateTimeImmutable('+10 days')));
    }

    public function testContentScoreGrowsAndCaps(): void
    {
        $l = $this->lawyer();
        for ($i = 0; $i < 3; $i++) { $l->registerPublishedArticle(); }
        self::assertSame(0.15, $l->getContentScore()); // 3 × 0.05

        for ($i = 0; $i < 20; $i++) { $l->registerPublishedArticle(); }
        self::assertSame(0.5, $l->getContentScore()); // кап +0.5
    }
}

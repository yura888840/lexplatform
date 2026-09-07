<?php
declare(strict_types=1);

namespace App\Domain\Lawyer\Service;

use App\Domain\Lawyer\Entity\LawyerProfile;

/**
 * Правило «статьи или деньги»:
 *  - MODE_ARTICLES: «бажано через день» → ok если последняя статья ≤ CADENCE_OK_DAYS,
 *    warning ≤ CADENCE_WARNING_DAYS, дальше non_compliant. Новичкам — грейс ONBOARDING_DAYS
 *    на первую статью в каждую выбранную отрасль.
 *  - MODE_PAID: ok при активной подписке «Юрист», иначе non_compliant.
 *
 * Санкция non_compliant: снятие featured + понижение в каталоге (профиль не скрывается —
 * бизнес этого не требовал, снижение видимости достаточно мотивирует).
 */
final class ComplianceChecker
{
    public const CADENCE_OK_DAYS = 3;       // «через день» + 1 день слака
    public const CADENCE_WARNING_DAYS = 7;
    public const ONBOARDING_DAYS = 14;

    /**
     * @param bool $hasActiveLawyerSubscription активная подписка плана lawyer-*
     * @param int  $specializationsCovered      число отраслей юриста, в которых есть ≥1 опубликованная статья
     */
    public function evaluate(
        LawyerProfile $lawyer,
        bool $hasActiveLawyerSubscription,
        int $specializationsCovered,
        \DateTimeImmutable $now = new \DateTimeImmutable(),
    ): string {
        if ($lawyer->getContributionMode() === LawyerProfile::MODE_PAID) {
            return $hasActiveLawyerSubscription
                ? LawyerProfile::COMPLIANCE_OK
                : LawyerProfile::COMPLIANCE_NON_COMPLIANT;
        }

        // MODE_ARTICLES
        $registeredDays = (int) $lawyer->getUser()->getCreatedAt()->diff($now)->format('%a');
        $specializationsTotal = $lawyer->getSpecializations()->count();

        // Онбординг: 14 дней на первую статью
        if ($lawyer->getArticlesPublishedCount() === 0) {
            return $registeredDays <= self::ONBOARDING_DAYS
                ? LawyerProfile::COMPLIANCE_ONBOARDING
                : LawyerProfile::COMPLIANCE_NON_COMPLIANT;
        }

        // Каждая выбранная отрасль должна быть подтверждена статьёй
        $coverageIncomplete = $specializationsTotal > 0 && $specializationsCovered < $specializationsTotal;

        $lastAt = $lawyer->getLastArticleAt();
        $daysSinceLast = $lastAt === null
            ? PHP_INT_MAX
            : (int) $lastAt->diff($now)->format('%a');

        if ($daysSinceLast > self::CADENCE_WARNING_DAYS) {
            return LawyerProfile::COMPLIANCE_NON_COMPLIANT;
        }
        if ($daysSinceLast > self::CADENCE_OK_DAYS || $coverageIncomplete) {
            return LawyerProfile::COMPLIANCE_WARNING;
        }
        return LawyerProfile::COMPLIANCE_OK;
    }
}

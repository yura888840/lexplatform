<?php
declare(strict_types=1);

namespace App\UI\Console;

use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Service\ComplianceChecker;
use App\Domain\Payment\Entity\Subscription;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ночной пересчёт compliance всех юристов (cron/K8s CronJob).
 * non_compliant → снятие featured (санкция правила «статьи или деньги»).
 */
#[AsCommand(name: 'app:compliance-recheck', description: 'Пересчёт compliance-статусов юристов')]
final class ComplianceRecheckCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly ComplianceChecker $checker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stats = ['ok' => 0, 'warning' => 0, 'non_compliant' => 0, 'onboarding' => 0];

        foreach ($this->em->getRepository(LawyerProfile::class)->findAll() as $lawyer) {
            $covered = (int) $this->db->fetchOne(
                "SELECT COUNT(DISTINCT a.specialization_id) FROM articles a
                 WHERE a.author_id = :uid AND a.status = 'published' AND a.specialization_id IS NOT NULL",
                ['uid' => $lawyer->getUser()->getId()->toRfc4122()]
            );
            $sub = $this->em->getRepository(Subscription::class)->findOneBy(
                ['user' => $lawyer->getUser(), 'status' => Subscription::STATUS_ACTIVE], ['expiresAt' => 'DESC']
            );
            $hasLawyerSub = $sub !== null && $sub->isActive() && str_starts_with($sub->getPlan()->getSlug(), 'lawyer');

            $status = $this->checker->evaluate($lawyer, $hasLawyerSub, $covered);
            $lawyer->setComplianceStatus($status);
            if ($status === LawyerProfile::COMPLIANCE_NON_COMPLIANT) {
                $lawyer->setFeatured(false);
            }
            $stats[$status] = ($stats[$status] ?? 0) + 1;
        }

        $this->em->flush();
        $output->writeln(sprintf(
            '<info>✔ ok: %d | warning: %d | non_compliant: %d | onboarding: %d</info>',
            $stats['ok'], $stats['warning'], $stats['non_compliant'], $stats['onboarding']
        ));
        return Command::SUCCESS;
    }
}

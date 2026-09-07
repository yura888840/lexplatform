<?php
declare(strict_types=1);

namespace App\Domain\Lawyer\Entity;

use App\Domain\Identity\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'lawyer_profiles')]
#[ORM\Index(name: 'idx_lawyer_rating', columns: ['rating'])]
#[ORM\Index(name: 'idx_lawyer_featured', columns: ['is_featured', 'rating'])]
#[ORM\HasLifecycleCallbacks]
class LawyerProfile
{
    /** Режим участия: статьи (бесплатно) или платная подписка «Юрист». */
    public const MODE_ARTICLES = 'articles';
    public const MODE_PAID = 'paid';

    public const COMPLIANCE_ONBOARDING = 'onboarding';
    public const COMPLIANCE_OK = 'ok';
    public const COMPLIANCE_WARNING = 'warning';
    public const COMPLIANCE_NON_COMPLIANT = 'non_compliant';

    /** +0.05 к рейтингу за опубликованную статью, максимум +0.5 (бизнес: «рейтинг підвищується від кількості статей»). */
    public const CONTENT_SCORE_PER_ARTICLE = 0.05;
    public const CONTENT_SCORE_CAP = 0.5;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(name: 'bar_number', length: 100, nullable: true)]
    private ?string $barNumber = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $bio = '';

    #[ORM\Column(name: 'experience_years')]
    private int $experienceYears = 0;

    #[ORM\Column(name: 'hourly_rate', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $hourlyRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    private string $rating = '0.00';

    #[ORM\Column(name: 'reviews_count')]
    private int $reviewsCount = 0;

    #[ORM\Column(name: 'consultations_count')]
    private int $consultationsCount = 0;

    #[ORM\Column(name: 'response_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $responseRate = '0.00';

    #[ORM\Column(name: 'response_time_avg')]
    private int $responseTimeAvgMinutes = 0;

    #[ORM\Column(length: 100)]
    private string $city = '';

    #[ORM\Column(length: 100)]
    private string $region = '';

    #[ORM\Column(name: 'is_online')]
    private bool $isOnline = false;

    #[ORM\Column(name: 'is_verified')]
    private bool $isVerified = false;

    #[ORM\Column(name: 'is_featured')]
    private bool $isFeatured = false;

    #[ORM\Column(name: 'profile_completeness')]
    private int $profileCompleteness = 0;

    #[ORM\Column(name: 'video_consultation_enabled')]
    private bool $videoConsultationEnabled = false;

    #[ORM\Column(name: 'contribution_mode', length: 20)]
    private string $contributionMode = self::MODE_ARTICLES;

    #[ORM\Column(name: 'articles_published_count')]
    private int $articlesPublishedCount = 0;

    #[ORM\Column(name: 'last_article_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastArticleAt = null;

    #[ORM\Column(name: 'compliance_status', length: 20)]
    private string $complianceStatus = self::COMPLIANCE_ONBOARDING;

    #[ORM\Column(name: 'content_score', type: Types::DECIMAL, precision: 3, scale: 2)]
    private string $contentScore = '0.00';

    /** @var Collection<int, Specialization> */
    #[ORM\ManyToMany(targetEntity: Specialization::class)]
    #[ORM\JoinTable(name: 'lawyer_specializations')]
    #[ORM\JoinColumn(name: 'lawyer_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'specialization_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $specializations;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $slug)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->slug = $slug;
        $this->specializations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getSlug(): string { return $this->slug; }
    public function getBio(): string { return $this->bio; }
    public function setBio(string $bio): void { $this->bio = $bio; }
    public function getBarNumber(): ?string { return $this->barNumber; }
    public function setBarNumber(?string $n): void { $this->barNumber = $n; }
    public function getExperienceYears(): int { return $this->experienceYears; }
    public function setExperienceYears(int $y): void { $this->experienceYears = $y; }
    public function getHourlyRate(): ?string { return $this->hourlyRate; }
    public function setHourlyRate(?string $rate): void { $this->hourlyRate = $rate; }
    public function getRating(): float { return (float) $this->rating; }
    public function getReviewsCount(): int { return $this->reviewsCount; }
    public function getConsultationsCount(): int { return $this->consultationsCount; }
    public function incrementConsultations(): void { $this->consultationsCount++; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $city): void { $this->city = $city; }
    public function getRegion(): string { return $this->region; }
    public function setRegion(string $region): void { $this->region = $region; }
    public function isOnline(): bool { return $this->isOnline; }
    public function setOnline(bool $online): void { $this->isOnline = $online; }
    public function isVerified(): bool { return $this->isVerified; }
    public function verify(): void { $this->isVerified = true; }
    public function isFeatured(): bool { return $this->isFeatured; }
    public function setFeatured(bool $f): void { $this->isFeatured = $f; }
    public function isVideoConsultationEnabled(): bool { return $this->videoConsultationEnabled; }

    /** @return Collection<int, Specialization> */
    public function getSpecializations(): Collection { return $this->specializations; }
    public function addSpecialization(Specialization $s): void
    {
        if (!$this->specializations->contains($s)) {
            $this->specializations->add($s);
        }
    }

    public function getContributionMode(): string { return $this->contributionMode; }

    public function switchContributionMode(string $mode): void
    {
        if (!in_array($mode, [self::MODE_ARTICLES, self::MODE_PAID], true)) {
            throw new \DomainException('Недопустимый режим участия.');
        }
        $this->contributionMode = $mode;
    }

    public function getArticlesPublishedCount(): int { return $this->articlesPublishedCount; }
    public function getLastArticleAt(): ?\DateTimeImmutable { return $this->lastArticleAt; }
    public function getComplianceStatus(): string { return $this->complianceStatus; }
    public function setComplianceStatus(string $status): void { $this->complianceStatus = $status; }
    public function getContentScore(): float { return (float) $this->contentScore; }

    /** Эффективный рейтинг каталога: отзывы + контент-бонус, кап 5.0. */
    public function getEffectiveRating(): float
    {
        return min(5.0, $this->getRating() + $this->getContentScore());
    }

    /** Вызывается при публикации статьи юриста (событие ArticlePublished). */
    public function registerPublishedArticle(): void
    {
        $this->articlesPublishedCount++;
        $this->lastArticleAt = new \DateTimeImmutable();
        $this->contentScore = number_format(
            min(self::CONTENT_SCORE_CAP, $this->articlesPublishedCount * self::CONTENT_SCORE_PER_ARTICLE),
            2, '.', ''
        );
    }

    /** Пересчёт рейтинга при новом одобренном отзыве (событие ReviewSubmitted, ТЗ §9.2). */
    public function applyReview(int $stars): void
    {
        $total = $this->getRating() * $this->reviewsCount + $stars;
        $this->reviewsCount++;
        $this->rating = number_format($total / $this->reviewsCount, 2, '.', '');
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Lawyer\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Документ верификации: свідоцтво адвоката (обязательно) / про освіту (желательно). */
#[ORM\Entity]
#[ORM\Table(name: 'verification_documents')]
#[ORM\Index(name: 'idx_verif_docs_lawyer', columns: ['lawyer_id', 'status'])]
class VerificationDocument
{
    public const TYPE_ADVOCATE_CERTIFICATE = 'advocate_certificate';
    public const TYPE_EDUCATION_CERTIFICATE = 'education_certificate';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ALLOWED_TYPES = [self::TYPE_ADVOCATE_CERTIFICATE, self::TYPE_EDUCATION_CERTIFICATE];
    public const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: LawyerProfile::class)]
    #[ORM\JoinColumn(name: 'lawyer_id', nullable: false, onDelete: 'CASCADE')]
    private LawyerProfile $lawyer;

    #[ORM\Column(length: 40)]
    private string $type;

    #[ORM\Column(name: 'file_url', length: 700)]
    private string $fileUrl;

    #[ORM\Column(name: 'file_name', length: 300)]
    private string $fileName;

    #[ORM\Column(name: 'mime_type', length: 100)]
    private string $mimeType;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'review_note', type: Types::TEXT, nullable: true)]
    private ?string $reviewNote = null;

    #[ORM\Column(name: 'uploaded_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(name: 'reviewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    public function __construct(LawyerProfile $lawyer, string $type, string $fileUrl, string $fileName, string $mimeType)
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \DomainException('Недопустимый тип документа.');
        }
        $this->id = Uuid::v7();
        $this->lawyer = $lawyer;
        $this->type = $type;
        $this->fileUrl = $fileUrl;
        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getLawyer(): LawyerProfile { return $this->lawyer; }
    public function getType(): string { return $this->type; }
    public function getFileUrl(): string { return $this->fileUrl; }
    public function getFileName(): string { return $this->fileName; }
    public function getStatus(): string { return $this->status; }
    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }

    /** Одобрение свідоцтва адвоката верифицирует профиль. */
    public function approve(?string $note = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->reviewNote = $note;
        $this->reviewedAt = new \DateTimeImmutable();
        if ($this->type === self::TYPE_ADVOCATE_CERTIFICATE) {
            $this->lawyer->verify();
        }
    }

    public function reject(string $note): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->reviewNote = $note;
        $this->reviewedAt = new \DateTimeImmutable();
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\LegalBase\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Суд (ТЗ §10.1: courts). */
#[ORM\Entity]
#[ORM\Table(name: 'courts')]
class Court
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 300)]
    private string $name;

    /** supreme | appellate | local | commercial | administrative */
    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 100)]
    private string $region;

    public function __construct(string $name, string $type, string $region = '')
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->type = $type;
        $this->region = $region;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getRegion(): string { return $this->region; }
}

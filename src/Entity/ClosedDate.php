<?php

namespace App\Entity;

use App\Repository\ClosedDateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClosedDateRepository::class)]
#[ORM\Table(name: 'closed_date')]
class ClosedDate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    private \DateTimeImmutable $seatingDate;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(\DateTimeImmutable $seatingDate)
    {
        $this->seatingDate = $seatingDate;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeatingDate(): \DateTimeImmutable
    {
        return $this->seatingDate;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

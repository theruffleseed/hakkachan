<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use App\Reservation\GuestDetails;
use App\Reservation\Pricing;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $seatingDate;

    #[ORM\Column]
    private int $pax;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $guestEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $guestName = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $guestPhone = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        \DateTimeImmutable $seatingDate,
        int $pax,
        int $amountCents,
        string $guestName,
        ?string $guestPhone,
        ?string $guestEmail,
    ) {
        $this->seatingDate = $seatingDate;
        $this->pax = $pax;
        $this->amountCents = $amountCents;
        $this->guestName = $guestName;
        $this->guestPhone = $guestPhone;
        $this->guestEmail = $guestEmail;
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

    public function getPax(): int
    {
        return $this->pax;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setSeatingDate(\DateTimeImmutable $seatingDate): void
    {
        $this->seatingDate = $seatingDate;
    }

    /**
     * The amount follows the pax count so the record stays internally
     * consistent. It records what the booking is worth, not what Stripe
     * charged — an admin edit moves neither money nor receipts.
     */
    public function setPax(int $pax): void
    {
        $this->pax = $pax;
        $this->amountCents = Pricing::amountCents($pax);
    }

    public function setGuest(GuestDetails $guest): void
    {
        $this->guestName = $guest->name;
        $this->guestPhone = $guest->phone;
        $this->guestEmail = $guest->email;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markPaid(): void
    {
        $this->status = 'paid';
    }

    public function getGuestEmail(): ?string
    {
        return $this->guestEmail;
    }

    public function getGuestName(): ?string
    {
        return $this->guestName;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(string $stripeSessionId): void
    {
        $this->stripeSessionId = $stripeSessionId;
    }
}

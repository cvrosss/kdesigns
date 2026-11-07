<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One order line: a customer buys a quantity of a single product. Product name,
 * price and image are snapshots, so history survives later catalog edits.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_status', fields: ['status'])]
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_READY, self::STATUS_SUCCESSFUL, self::STATUS_DELIVERED, self::STATUS_CANCELLED];
    public const STATUS_LABELS = [self::STATUS_READY => 'Ready for Pick up'];

    public const PAYMENT_AT_SHOP = 'Pay at Shop';
    public const PAYMENT_METHODS = [self::PAYMENT_AT_SHOP, 'GCash', 'BDO', 'BPI'];

    public const FULFILLMENT_PICKUP = 'pickup';
    public const FULFILLMENT_DELIVERY = 'delivery';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Kept for stock return; null if the product was later deleted. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 150)]
    private ?string $productName = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $unitPrice = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(length: 255)]
    private ?string $productImage = null;

    #[ORM\Column(length: 150)]
    private ?string $customerName = null;

    #[ORM\Column(length: 50)]
    private ?string $contact = null;

    #[ORM\Column(length: 10)]
    private string $fulfillment = self::FULFILLMENT_PICKUP;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateNeeded = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $timeNeeded = null;

    #[ORM\Column(length: 20)]
    private string $paymentMethod = 'Pay at Shop';

    /** Payment reference (KD########) shown on the e-receipt; only set for online payment methods. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $receiptRef = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $deliveryReceiver = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $deliveryContact = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deliveryLocation = null;

    #[ORM\Column(length: 12)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    public function getProductImage(): ?string
    {
        return $this->productImage;
    }

    public function setProductImage(string $productImage): static
    {
        $this->productImage = $productImage;

        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getFulfillment(): string
    {
        return $this->fulfillment;
    }

    public function setFulfillment(string $fulfillment): static
    {
        $this->fulfillment = $fulfillment;

        return $this;
    }

    public function getDateNeeded(): ?\DateTimeImmutable
    {
        return $this->dateNeeded;
    }

    public function setDateNeeded(?\DateTimeImmutable $dateNeeded): static
    {
        $this->dateNeeded = $dateNeeded;

        return $this;
    }

    public function getTimeNeeded(): ?string
    {
        return $this->timeNeeded;
    }

    public function setTimeNeeded(?string $timeNeeded): static
    {
        $this->timeNeeded = $timeNeeded;

        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getReceiptRef(): ?string
    {
        return $this->receiptRef;
    }

    public function setReceiptRef(?string $receiptRef): static
    {
        $this->receiptRef = $receiptRef;

        return $this;
    }

    public function getDeliveryReceiver(): ?string
    {
        return $this->deliveryReceiver;
    }

    public function setDeliveryReceiver(?string $deliveryReceiver): static
    {
        $this->deliveryReceiver = $deliveryReceiver;

        return $this;
    }

    public function getDeliveryContact(): ?string
    {
        return $this->deliveryContact;
    }

    public function setDeliveryContact(?string $deliveryContact): static
    {
        $this->deliveryContact = $deliveryContact;

        return $this;
    }

    public function getDeliveryLocation(): ?string
    {
        return $this->deliveryLocation;
    }

    public function setDeliveryLocation(?string $deliveryLocation): static
    {
        $this->deliveryLocation = $deliveryLocation;

        return $this;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    /**
     * Statuses an admin may set. A pick-up order can be marked Ready for Pick up and ends as
     * Successful; a delivery order ends as Delivered.
     *
     * @return list<string>
     */
    public function allowedStatuses(): array
    {
        return self::FULFILLMENT_DELIVERY === $this->fulfillment
            ? [self::STATUS_PENDING, self::STATUS_DELIVERED, self::STATUS_CANCELLED]
            : [self::STATUS_PENDING, self::STATUS_READY, self::STATUS_SUCCESSFUL, self::STATUS_CANCELLED];
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

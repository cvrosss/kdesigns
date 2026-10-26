<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\InsufficientStockException;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    private const METHODS = ['Pick Up' => Order::FULFILLMENT_PICKUP, 'Delivery' => Order::FULFILLMENT_DELIVERY];

    /** Half-hour slots from 10:00 AM to 6:00 PM, matching the order form. */
    private const SLOTS = ['10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00'];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly OrderService $orders,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * Orders can only be placed by a signed-in customer; the check lives here, not in the browser.
     * Price, name and image come from the product row, never from the request.
     */
    #[Route('/orders', name: 'app_order_place', methods: ['POST'])]
    public function place(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Please log in or sign up to place an order.'], 401);
        }

        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            return $this->json(['error' => 'Unsupported request.'], 415);
        }

        $data = $request->toArray();
        $productId = $data['productId'] ?? null;
        $product = \is_scalar($productId) && ctype_digit((string) $productId) ? $this->products->find((int) $productId) : null;

        if (null === $product || $product->getStock() <= 0) {
            return $this->json(['error' => 'This product is not available.'], 422);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $contact = trim((string) ($data['contact'] ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['date'] ?? ''));
        $time = \DateTimeImmutable::createFromFormat('!H:i', (string) ($data['time'] ?? ''));
        $method = self::METHODS[$data['method'] ?? ''] ?? null;
        $quantity = (int) ($data['quantity'] ?? 1);
        $payment = $data['paymentMethod'] ?? Order::PAYMENT_AT_SHOP;

        if ('' === $name || mb_strlen($name) > 80) {
            return $this->json(['error' => 'Please enter your name.'], 422);
        }
        if (mb_strlen($contact) > 30 || \strlen(preg_replace('/\D/', '', $contact)) < 7) {
            return $this->json(['error' => 'Please enter a valid contact number.'], 422);
        }
        if (false === $date || $date < new \DateTimeImmutable('today')) {
            return $this->json(['error' => 'Please choose today or a later date.'], 422);
        }
        if (false === $time || !\in_array($time->format('H:i'), self::SLOTS, true)) {
            return $this->json(['error' => 'Please choose a time between 10:00 AM and 6:00 PM.'], 422);
        }
        if (null === $method) {
            return $this->json(['error' => 'Please choose Pick Up or Delivery.'], 422);
        }
        if (!\is_string($payment) || !\in_array($payment, Order::PAYMENT_METHODS, true)) {
            return $this->json(['error' => 'Please choose a payment method.'], 422);
        }
        if ($quantity < 1 || $quantity > 50) {
            return $this->json(['error' => 'Please choose a quantity between 1 and 50.'], 422);
        }

        $order = (new Order())
            ->setCustomerName($name)
            ->setContact($contact)
            ->setFulfillment($method)
            ->setDateNeeded($date)
            ->setTimeNeeded($time->format('H:i'))
            ->setPaymentMethod($payment);

        // Only online methods carry a payment reference; reuse the one shown on the e-receipt if it is well-formed.
        if (Order::PAYMENT_AT_SHOP !== $payment) {
            $submitted = (string) ($data['receiptRef'] ?? '');
            $taken = '' === $submitted || null !== $this->orderRepository->findOneBy(['receiptRef' => $submitted]);
            $order->setReceiptRef(preg_match('/^KD\d{8}$/', $submitted) && !$taken ? $submitted : 'KD'.random_int(10000000, 99999999));
        }

        if (Order::FULFILLMENT_DELIVERY === $method) {
            $receiver = trim((string) ($data['deliveryReceiver'] ?? '')) ?: $name;
            $receiverContact = trim((string) ($data['deliveryContact'] ?? '')) ?: $contact;
            $location = trim((string) ($data['deliveryLocation'] ?? ''));

            if ('' === $location || mb_strlen($location) > 500) {
                return $this->json(['error' => 'Please enter the delivery address.'], 422);
            }
            if (mb_strlen($receiver) > 150 || mb_strlen($receiverContact) > 50) {
                return $this->json(['error' => 'The receiver details are too long.'], 422);
            }

            $order->setDeliveryReceiver($receiver)->setDeliveryContact($receiverContact)->setDeliveryLocation($location);
        }

        try {
            $this->orders->place($user, $product, $quantity, $order);
        } catch (InsufficientStockException $e) {
            return $this->json([
                'error' => $e->available > 0
                    ? sprintf('Sorry — only %d left in stock. Please lower the quantity.', $e->available)
                    : 'Sorry — this item just sold out.',
            ], 409);
        }

        return $this->json([
            'status' => 'received',
            'reference' => $order->getReference(),
            'receiptRef' => $order->getReceiptRef(),
            'paymentMethod' => $order->getPaymentMethod(),
            'total' => $order->getTotal(),
        ], 201);
    }

    /** The signed-in customer's own orders as JSON (read-only: shown in the cart drawer with their live status). */
    #[Route('/orders', name: 'app_order_list', methods: ['GET'])]
    public function list(OrderRepository $orders): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['orders' => []]);
        }

        return $this->json(['orders' => array_map(static fn (Order $o) => [
            'reference' => $o->getReference(),
            'name' => $o->getProductName(),
            'quantity' => $o->getQuantity(),
            'total' => $o->getTotal(),
            'image' => '/'.$o->getProductImage(),
            'status' => $o->getStatus(),
            'fulfillment' => Order::FULFILLMENT_DELIVERY === $o->getFulfillment() ? 'Delivery' : 'Pick Up',
            'date' => $o->getDateNeeded()?->format('Y-m-d'),
            'time' => $o->getTimeNeeded(),
        ], $orders->findForUser($user))]);
    }

    /** The signed-in customer's own orders, newest first. */
    #[Route('/my-orders', name: 'app_my_orders', methods: ['GET'])]
    public function history(OrderRepository $orders): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('order/history.html.twig', ['orders' => $orders->findForUser($user)]);
    }
}

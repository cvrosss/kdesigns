<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\FeedbackRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\InsufficientStockException;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin dashboard. Access is restricted to ROLE_ADMIN here and by access_control in security.yaml.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
        private readonly OrderService $orderService,
    ) {
    }

    #[Route('', name: 'app_overview', methods: ['GET'])]
    public function overview(): Response
    {
        $counts = $this->orders->countByStatus();

        return $this->render('admin/overview.html.twig', [
            'stats' => [
                'revenue' => $this->orders->revenue(),
                'total_orders' => array_sum($counts),
                'pending_orders' => $counts[Order::STATUS_PENDING],
                'low_stock' => $this->products->countLowStock(self::LOW_STOCK_THRESHOLD),
                'active_products' => $this->products->countActive(),
                'total_products' => $this->products->count([]),
                'total_buyers' => $this->orders->countBuyers(),
            ],
            'recent_orders' => array_map($this->orderRow(...), $this->orders->findAllWithUser(5)),
            'stock_alerts' => array_map(static fn (Product $p) => [
                'name' => $p->getName(),
                'category' => $p->getCategory()->getCategoryname(),
                'stock' => $p->getStock(),
            ], $this->products->findLowStock(self::LOW_STOCK_THRESHOLD)),
        ]);
    }

    #[Route('/orders', name: 'app_orders', methods: ['GET'])]
    public function orders(Request $request): Response
    {
        $counts = $this->orders->countByStatus();
        $filter = $request->query->getString('status');
        $filter = \in_array($filter, Order::STATUSES, true) ? $filter : null;

        $rows = array_map($this->orderRow(...), $this->orders->findAllWithUser());
        if (null !== $filter) {
            $rows = array_values(array_filter($rows, static fn (array $r) => $r['status'] === $filter));
        }

        return $this->render('admin/orders.html.twig', [
            'orders' => $rows,
            'statuses' => Order::STATUSES,
            'status_counts' => $counts,
            'total_orders' => array_sum($counts),
            'active_status' => $filter,
        ]);
    }

    #[Route('/orders/{reference}/status', name: 'app_order_status', methods: ['POST'])]
    public function updateStatus(string $reference, Request $request): RedirectResponse
    {
        $order = $this->orders->findOneBy(['reference' => $reference]);
        if (null === $order) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('order_status_'.$reference, $request->request->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } else {
            try {
                $this->orderService->updateStatus($order, $request->request->getString('status'));
                $this->addFlash('admin_success', sprintf('Order %s updated.', $reference));
            } catch (\InvalidArgumentException) {
                $this->addFlash('admin_error', 'Unknown order status.');
            } catch (InsufficientStockException $e) {
                $this->addFlash('admin_error', sprintf('Cannot reinstate %s: only %d of "%s" in stock.', $reference, $e->available, $e->productName));
            }
        }

        return $this->redirectToRoute('app_orders', array_filter(['status' => $request->query->getString('status')]), Response::HTTP_SEE_OTHER);
    }

    #[Route('/inventory', name: 'app_inventory', methods: ['GET'])]
    public function inventory(Request $request): Response
    {
        $filter = $request->query->getString('filter');
        $products = $this->products->findCatalog();
        $total = \count($products);

        if ('low' === $filter) {
            $products = array_filter($products, static fn (Product $p) => $p->getStock() > 0 && $p->getStock() <= self::LOW_STOCK_THRESHOLD);
        } elseif ('out' === $filter) {
            $products = array_filter($products, static fn (Product $p) => $p->getStock() <= 0);
        } else {
            $filter = '';
        }

        return $this->render('admin/inventory.html.twig', [
            'products' => array_map(static fn (Product $p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'tag' => $p->getBadge(),
                'category' => $p->getCategory()->getCategoryname(),
                'price' => $p->getPrice(),
                'stock' => $p->getStock(),
                'image' => $p->getImage(),
            ], $products),
            'total_products' => $total,
            'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
            'active_filter' => $filter,
        ]);
    }

    #[Route('/inventory/{id}/stock', name: 'app_inventory_stock', methods: ['POST'])]
    public function setStock(Product $product, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('stock_'.$product->getId(), $request->request->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } else {
            $qty = filter_var($request->request->get('qty'), FILTER_VALIDATE_INT);
            if (false === $qty || $qty < 1) {
                $this->addFlash('admin_error', 'Enter a whole number of 1 or more to add to the stock.');
            } else {
                try {
                    $total = $this->orderService->restock($product, $qty);
                    $this->addFlash('admin_success', sprintf('%s: added %d — now %d in stock.', $product->getName(), $qty, $total));
                } catch (\InvalidArgumentException) {
                    $this->addFlash('admin_error', sprintf('Stock cannot exceed %d units.', Product::MAX_STOCK));
                }
            }
        }

        return $this->redirectToRoute('app_inventory', array_filter(['filter' => $request->query->getString('filter')]), Response::HTTP_SEE_OTHER);
    }

    #[Route('/buyers', name: 'app_buyers_history', methods: ['GET'])]
    public function buyersHistory(): Response
    {
        $buyers = $this->orders->buyersSummary();
        $revenue = $this->orders->revenue();
        $counts = $this->orders->countByStatus();
        $paidOrders = array_sum($counts) - $counts[Order::STATUS_CANCELLED];

        return $this->render('admin/buyers.html.twig', [
            'buyers' => $buyers,
            'stats' => [
                'total_buyers' => \count($buyers),
                'revenue' => $revenue,
                'average_order_value' => $paidOrders > 0 ? (int) round($revenue / $paidOrders) : 0,
            ],
        ]);
    }

    #[Route('/feedback', name: 'app_admin_feedback', methods: ['GET'])]
    public function feedback(FeedbackRepository $feedback): Response
    {
        return $this->render('admin/feedback.html.twig', ['reviews' => $feedback->findAllNewestFirst()]);
    }

    #[Route('/feedback/{id}', name: 'app_admin_feedback_update', methods: ['POST'])]
    public function updateFeedback(Feedback $review, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('feedback_'.$review->getId(), $request->request->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } else {
            switch ($request->request->getString('action')) {
                case 'publish':
                    $review->setStatus(Feedback::STATUS_PUBLISHED);
                    break;
                case 'hide':
                    $review->setStatus(Feedback::STATUS_HIDDEN);
                    break;
                case 'delete':
                    $em->remove($review);
                    break;
                default:
                    $this->addFlash('admin_error', 'Unknown action.');

                    return $this->redirectToRoute('app_admin_feedback', [], Response::HTTP_SEE_OTHER);
            }
            $em->flush();
            $this->addFlash('admin_success', 'Review updated.');
        }

        return $this->redirectToRoute('app_admin_feedback', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(Order $o): array
    {
        return [
            'id' => $o->getReference(),
            'customer' => $o->getUser()->getName(),
            'email' => $o->getUser()->getEmail(),
            'items' => [sprintf('%s × %d', $o->getProductName(), $o->getQuantity())],
            'total' => $o->getTotal(),
            'status' => $o->getStatus(),
            'statuses' => $o->allowedStatuses(),
            'date' => $o->getCreatedAt()->format('M j, Y'),
        ];
    }
}

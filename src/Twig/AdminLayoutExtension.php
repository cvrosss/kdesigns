<?php

namespace App\Twig;

use App\Controller\AdminController;
use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes layout-wide data (sidebar notification badges) to every template.
 * Counts are only queried on /admin pages.
 */
final class AdminLayoutExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
    ) {
    }

    public function getGlobals(): array
    {
        $badges = ['orders' => 0, 'inventory' => 0];

        if (str_starts_with($this->requests->getCurrentRequest()?->getPathInfo() ?? '', '/admin')) {
            $badges['orders'] = $this->orders->countByStatus()[Order::STATUS_PENDING];
            $badges['inventory'] = $this->products->countLowStock(AdminController::LOW_STOCK_THRESHOLD);
        }

        return ['nav_badges' => $badges];
    }
}

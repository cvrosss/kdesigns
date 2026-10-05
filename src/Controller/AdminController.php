<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin dashboard pages. Every view receives empty/zero defaults until a real
 * data source (repositories / services) is wired in.
 */
#[Route('/admin')]
final class AdminController extends AbstractController
{
    private const LOW_STOCK_THRESHOLD = 5;

    private const ORDER_STATUSES = ['pending', 'processing', 'delivered', 'cancelled'];

    #[Route('', name: 'app_overview')]
    public function overview(): Response
    {
        return $this->render('admin/overview.html.twig', [
            'stats' => [
                'revenue' => 0,
                'total_orders' => 0,
                'pending_orders' => 0,
                'low_stock' => 0,
                'active_products' => 0,
                'total_products' => 0,
                'total_buyers' => 0,
            ],
            'recent_orders' => [],
            'stock_alerts' => [],
        ]);
    }

    #[Route('/orders', name: 'app_orders')]
    public function orders(): Response
    {
        return $this->render('admin/orders.html.twig', [
            'orders' => [],
            'statuses' => self::ORDER_STATUSES,
            'status_counts' => array_fill_keys(self::ORDER_STATUSES, 0),
            'total_orders' => 0,
        ]);
    }

    #[Route('/inventory', name: 'app_inventory')]
    public function inventory(): Response
    {
        return $this->render('admin/inventory.html.twig', [
            'products' => [],
            'total_products' => 0,
            'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
        ]);
    }

    #[Route('/buyers', name: 'app_buyers_history')]
    public function buyersHistory(): Response
    {
        return $this->render('admin/buyers.html.twig', [
            'buyers' => [],
            'stats' => [
                'total_buyers' => 0,
                'revenue' => 0,
                'average_order_value' => 0,
            ],
        ]);
    }
}

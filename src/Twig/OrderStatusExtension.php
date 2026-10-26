<?php

namespace App\Twig;

use App\Entity\Order;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * `{{ order.status|order_status }}` turns a stored status into its customer-facing label.
 */
final class OrderStatusExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('order_status', Order::statusLabel(...))];
    }
}

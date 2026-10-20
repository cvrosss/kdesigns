<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Site-wide storefront data used by the navbar, hero and footer.
 */
final class StorefrontExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            // Replace with the real cart count once a cart exists.
            'cart_count' => 0,
            'studio' => [
                'name' => 'KDesigns',
                'full_name' => 'KDesigns Blooms & Styles',
                'tagline' => 'Turning flowers into timeless memories.',
                'description' => 'Premier floral arrangements dedicated to botanical storytelling and intentional beauty.',
                'hours' => [
                    'We are open 10am to 6pm Monday to Saturday.',
                    'We only accept pre order on Sundays.',
                ],
                'social' => [
                    ['label' => 'Instagram', 'url' => '#'],
                    ['label' => 'Facebook', 'url' => '#'],
                    ['label' => 'TikTok', 'url' => '#'],
                ],
                'studio_links' => [
                    ['label' => 'About Us', 'route' => 'app_home', 'fragment' => 'about'],
                    ['label' => 'Our Process', 'route' => 'app_home', 'fragment' => 'about'],
                    ['label' => 'Sustainability', 'route' => 'app_home', 'fragment' => 'about'],
                ],
                'services' => ['Fresh and Dried Bouquets', 'Korean Baskets', 'Bloom Box'],
                'contact' => [
                    'email' => 'kdesigns@gmail.com',
                    'phone' => '+63 905 648 7476',
                    'city' => 'Dumaguete City',
                ],
            ],
        ];
    }
}

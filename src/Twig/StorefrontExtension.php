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
            'story' => [
                'years' => 3,
                'paragraphs' => [
                    'KDesigns Blooms & Styles is a premier floral design studio rooted in the belief that every individual bloom carries its own distinct meaning. We craft immersive botanical experiences by celebrating the raw beauty of seasonal stems, prioritizing organic textures, and allowing the natural architecture of the flowers to guide our intentional designs.',
                    "Founded by Claire Ann Ross with a deep reverence for nature's palette, each arrangement is a considered composition — never forced, always in conversation with the light, the space, and the people within it.",
                ],
                'stats' => [
                    ['value' => '98%', 'label' => 'Client Satisfaction'],
                    ['value' => '10', 'label' => 'Workshops Attended'],
                ],
                'image_main' => 'images/story-main.jpg',
                'image_secondary' => 'images/story-secondary.jpg',
            ],
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
                    ['label' => 'About Us', 'route' => 'app_about', 'fragment' => null],
                    ['label' => 'Our Process', 'route' => 'app_about', 'fragment' => null],
                    ['label' => 'Sustainability', 'route' => 'app_about', 'fragment' => null],
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

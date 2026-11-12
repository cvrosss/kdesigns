<?php

namespace App\Twig;

use App\Service\OrderSchedule;
use Twig\Extension\AbstractExtension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Site-wide storefront data used by the navbar, hero and footer.
 */
final class StorefrontExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /**
     * asset_v('/css/admin.css') returns the path with ?v=<file modified time>, so browsers reload it after every change.
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('asset_v', function (string $path): string {
            $file = $this->projectDir.'/public'.$path;

            return $path.(is_file($file) ? '?v='.filemtime($file) : '');
        })];
    }

    public function getGlobals(): array
    {
        return [
            'order_schedule' => ['timezone' => OrderSchedule::TIMEZONE, 'cutoff' => OrderSchedule::SAME_DAY_CUTOFF],
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
                // A '#' url renders in the footer as plain text instead of a dead link.
                'social' => [
                    ['label' => 'Instagram', 'url' => 'https://www.instagram.com/kdesignsbloomsandstyle'],
                    ['label' => 'Facebook', 'url' => 'https://www.facebook.com/profile.php?id=100090915814063'],
                    ['label' => 'TikTok', 'url' => 'https://www.tiktok.com/@kdesignsbloomsandstyle'],
                ],
                // Every entry must lead somewhere different: three labels pointing at the same
                // About page read as three sections that do not exist.
                'studio_links' => [
                    ['label' => 'About Us', 'route' => 'app_about', 'fragment' => null],
                    ['label' => 'Browse the Catalog', 'route' => 'app_catalog', 'fragment' => null],
                ],
                'services' => ['Fresh and Dried Bouquets', 'Korean Baskets', 'Basket Bouquet'],
                'contact' => [
                    'email' => 'kdesigns@gmail.com',
                    'phone' => '+63 905 648 7476',
                    'city' => 'Dumaguete City',
                ],
            ],
        ];
    }
}

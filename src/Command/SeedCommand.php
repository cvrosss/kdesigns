<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Feedback;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Loads the starter catalog, testimonials and (optionally) an admin account.
 * Safe to re-run: rows are matched by slug / name / email and existing ones are left untouched,
 * so stock levels edited by staff are never reset.
 */
#[AsCommand(name: 'app:seed', description: 'Load the starter catalog, testimonials and an admin account (idempotent)')]
final class SeedCommand extends Command
{
    private const CATEGORIES = [
        'fresh-bouquet' => 'Fresh Bouquet',
        'dried-bouquet' => 'Dried Bouquet',
        'basket-bouquet' => 'Basket Bouquet',
        'glass-dome' => 'Glass Dome',
        'others' => 'Others',
    ];

    private const GENERIC = 'A curated seasonal arrangement — update this description to match your product.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Login identifier of the admin account (an email, or a plain username like "admin")')
            ->addOption('admin-name', null, InputOption::VALUE_REQUIRED, 'Display name for a new admin', 'KDesigns Admin')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Admin password, hashed before storing. Sets/changes it; if omitted a NEW admin gets a random one (printed once)')
            ->addOption('staff-email', null, InputOption::VALUE_REQUIRED, 'Email of a staff account to create (ROLE_STAFF)')
            ->addOption('staff-name', null, InputOption::VALUE_REQUIRED, 'Display name for a new staff account', 'KDesigns Staff')
            ->addOption('staff-password', null, InputOption::VALUE_REQUIRED, 'Staff password; if omitted a NEW account gets a random one (printed once)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $categories = [];
        foreach (self::CATEGORIES as $slug => $label) {
            $category = $this->em->getRepository(Category::class)->findOneBy(['slug' => $slug]);
            if (null === $category) {
                $category = (new Category())->setSlug($slug)->setCategoryname($label)->setDescription($label.' arrangements');
                $this->em->persist($category);
            }
            $categories[$slug] = $category;
        }

        $created = 0;
        foreach ($this->products() as $i => [$name, $slug, $description, $price, $stock, $badge, $image]) {
            if (null !== $this->em->getRepository(Product::class)->findOneBy(['name' => $name])) {
                continue;
            }
            $this->em->persist(
                (new Product())->setName($name)->setCategory($categories[$slug])->setDescription($description)
                    ->setPrice($price)->setStock($stock)->setBadge($badge)->setImage($image)->setSortOrder($i + 1)
            );
            ++$created;
        }

        $reviews = 0;
        if (0 === $this->em->getRepository(Feedback::class)->count([])) {
            foreach ($this->testimonials() as [$author, $date, $quote]) {
                $this->em->persist((new Feedback())->setName($author)->setMessage($quote)->setCreatedAt(new \DateTimeImmutable($date)));
                ++$reviews;
            }
        }

        $this->em->flush();
        $io->success(sprintf('Catalog ready: %d new product(s), %d new review(s).', $created, $reviews));

        if (null !== ($email = $input->getOption('admin-email'))) {
            $this->ensureAccount($io, 'ROLE_ADMIN', mb_strtolower(trim($email)), (string) $input->getOption('admin-name'), $input->getOption('admin-password'));
        }
        if (null !== ($email = $input->getOption('staff-email'))) {
            $this->ensureAccount($io, 'ROLE_STAFF', mb_strtolower(trim($email)), (string) $input->getOption('staff-name'), $input->getOption('staff-password'));
        }

        return Command::SUCCESS;
    }

    private function ensureAccount(SymfonyStyle $io, string $role, string $email, string $name, ?string $password): void
    {
        $label = 'ROLE_ADMIN' === $role ? 'admin' : 'staff';
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (null !== $user) {
            $user->setPrimaryRole($role);
            $changed = null !== $password && '' !== $password;
            if ($changed) {
                $user->setPassword($this->hasher->hashPassword($user, $password));
            }
            $this->em->flush();
            $io->success(sprintf('%s is %s (password %s).', $email, $label, $changed ? 'updated' : 'unchanged'));

            return;
        }

        $generated = null === $password || '' === $password;
        $password = $generated ? bin2hex(random_bytes(9)) : $password;

        $user = (new User())->setEmail($email)->setName($name)->setPrimaryRole($role);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('%s account %s created.', ucfirst($label), $email));
        if ($generated) {
            $io->warning('Generated password (shown once): '.$password);
        }
    }

    /**
     * @return list<array{string, string, string, int, int, ?string, string}>
     */
    private function products(): array
    {
        $img = static fn (string $f) => 'images/products/'.$f.'.jpg';

        return [
            ['Crimson Romance Bouquet', 'fresh-bouquet', "Lush red roses with baby's breath and eucalyptus, hand-tied with silk ribbon.", 1850, 12, 'Bestseller', $img('crimson-romance-bouquet')],
            ['Pastel Peony Bouquet', 'fresh-bouquet', 'Soft blush peonies and garden roses in a dreamy pastel palette.', 2200, 5, 'Limited', $img('pastel-peony-bouquet')],
            ['Sunflower & Wildflower Mix', 'fresh-bouquet', 'Cheerful sunflowers paired with seasonal wildflowers and greenery.', 1200, 20, null, $img('sunflower-wildflower-mix')],
            ['Orchid Elegance Vase', 'fresh-bouquet', 'Cascading white orchids in a handcrafted ceramic vase — a statement piece.', 3500, 3, 'Luxury', $img('orchid-elegance-vase')],
            ['Garden Table Centerpiece', 'dried-bouquet', 'Lush garden-style centerpiece with seasonal blooms, foliage, and dried botanicals.', 2800, 8, null, $img('garden-table-centerpiece')],
            ['Lavender Dreams Bundle', 'dried-bouquet', 'Fragrant dried lavender bundles, perfect for home décor or gifts.', 980, 15, null, $img('lavender-dreams-bundle')],
            ['Tropical Bloom Arrangement', 'dried-bouquet', 'Bold heliconias, anthuriums, and tropical foliage in an architectural composition.', 3200, 4, 'New', $img('tropical-bloom-arrangement')],
            ['Bridal White Cascade', 'dried-bouquet', 'All-white cascading bridal bouquet with garden roses, stephanotis, and lily of the valley.', 4800, 2, 'Luxury', $img('bridal-white-cascade')],
            ['Citrus Sunrise Basket', 'basket-bouquet', 'A rustic woven basket arrangement featuring a prominent yellow lily, a bright orange rose, and textured green and yellow blooms. The design is finished with delicate filler flowers, eucalyptus leaves, and a branded KDesigns tag.', 2200, 5, null, $img('citrus-sunrise-basket')],
            ['Spring Elegance Basket', 'basket-bouquet', 'A round woven basket tied with a branded ribbon, highlighting a bold yellow lily and a bright pink gerbera daisy. The arrangement is complemented by coral carnations, soft pink blooms, and tall eucalyptus stems that provide height and structure.', 2400, 5, null, $img('spring-elegance-basket')],
            ['Soft Blush Meadow Basket', 'basket-bouquet', 'Presented in a handled woven basket tied with a white satin ribbon, this romantic arrangement is packed with pink gerbera daisies and crisp white flowers. It is softened with abundant baby\'s breath and fresh green accents.', 2800, 5, null, $img('soft-blush-meadow-basket')],
            ['Stargazer Pastel Basket', 'basket-bouquet', 'Set in a unique footed rectangular basket wrapped in a branded ribbon, this display centers around a striking pink stargazer lily. The lily is surrounded by a pastel mix of soft pink carnations, pale yellow flowers, delicate light blue accents, and baby\'s breath.', 3200, 5, null, $img('stargazer-pastel-basket')],
            ['Serenade Bloom Box', 'others', 'A chic white cylindrical box arrangement featuring a prominent yellow lily and a soft purple rose, delicately accented with white filler flowers and tall greenery.', 1500, 5, null, $img('serenade-bloom-box')],
            ['Floral Stand', 'others', 'A grand, multi-tiered standing arrangement bursting with colorful mixed blooms and lush tropical greenery. Draped with custom ribbons for personalized messages, this large display is ideal for inaugurations, grand openings, or formal tributes.', 3000, 5, null, $img('floral-stand')],
            ['Lavender Dreams Preserved Dome', 'glass-dome', 'A delicate preserved rose in a soft lilac hue, nestled amongst vibrant purple textural foliage and mixed dried florals, enclosed in a clear glass dome on a wooden base.', 1800, 5, null, $img('lavender-dreams-preserved-dome')],
            ['Crimson & Snow Preserved Dome', 'glass-dome', 'A striking contrast featuring a pristine white preserved rose surrounded by bold red dried foliage and delicate white filler flowers, beautifully presented in a glass dome.', 2100, 5, null, $img('crimson-snow-preserved-dome')],
            ['Natural Elegance Preserved Dome', 'glass-dome', 'An understated and elegant arrangement featuring two white preserved roses set against a backdrop of neutral, earthy dried grasses, wheat, and soft beige foliage, all within a classic glass dome.', 3000, 5, null, $img('natural-elegance-preserved-dome')],
            ['Rose Quartz Preserved Dome', 'glass-dome', 'A romantic design showcasing a single white preserved rose enhanced with vibrant pink dried accents, soft pink petals at the base, and fluffy textures, preserved in a glass dome.', 3500, 5, null, $img('rose-quartz-preserved-dome')],
            ['Sunflower Medallion Corsages', 'others', "A set of decorative corsages or medals featuring a central white flower surrounded by dried earthy blooms, baby's breath, and feathery accents. The arrangement rests on a bright yellow, flower-shaped base and hangs from a patterned golden-yellow ribbon. Priced per piece.", 450, 5, null, $img('sunflower-medallion-corsages')],
            ['Spring Delight Gift Bag', 'others', "A lively arrangement presented in a pink, purse-style gift box with a branded KDesigns ribbon. It features a pastel palette including a prominent pink rose, light peach and yellow blooms, textured green button mums, delicate white Queen Anne's lace, eucalyptus leaves, and bright blue feathery filler.", 1200, 5, null, $img('spring-delight-gift-bag')],
        ];
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function testimonials(): array
    {
        return [
            ['Hannah Montana', '2026-01-05 10:00:00', 'Thank you kaayo sa inyo arrangement <3 gwapa kaayo ag bulak and happy kaayo ang ako miga intawon pag dawat niya.'],
            ['Donny Pangilinan', '2024-06-14 09:00:00', "OMG, Thankk youuu kaayoo for the Prettyy flowerss!! 😭🩶 I didn't expect it to be this big and very fressh sa personal. I'm amazed by how it was delivered securely and on time huhu. Thank you so much for making my day soo wonderful as this flowers. We will surely be ordering again! 🫶💗"],
            ['Annabelle', '2025-03-22 14:30:00', 'Huge thank you for the stunning arrangement you put together for my anniversary! My partner was absolutely blown away by how huge and vibrant the bouquet was, and it honestly made our whole day so much more special. The delivery was right on time, the packaging was super cute, and the blooms are still looking perfect days later. You guys are the absolute best!'],
            ['Sarah De Guzman', '2026-02-01 11:15:00', "Absolutely stunning work. The flowers were incredibly fresh, and the composition was effortless and elegant. It's rare to see such thoughtful attention to detail in floral design. I couldn't be happier!"],
        ];
    }
}

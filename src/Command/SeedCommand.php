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
        'bloom-box' => 'Bloom Box',
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
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Admin password, hashed before storing. Sets/changes it; if omitted a NEW admin gets a random one (printed once)');
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
            $this->ensureAdmin($io, mb_strtolower(trim($email)), (string) $input->getOption('admin-name'), $input->getOption('admin-password'));
        }

        return Command::SUCCESS;
    }

    private function ensureAdmin(SymfonyStyle $io, string $email, string $name, ?string $password): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (null !== $user) {
            $user->setRoles(array_values(array_unique([...$user->getRoles(), 'ROLE_ADMIN'])));
            $changed = null !== $password && '' !== $password;
            if ($changed) {
                $user->setPassword($this->hasher->hashPassword($user, $password));
            }
            $this->em->flush();
            $io->success(sprintf('%s is an admin (password %s).', $email, $changed ? 'updated' : 'unchanged'));

            return;
        }

        $generated = null === $password || '' === $password;
        $password = $generated ? bin2hex(random_bytes(9)) : $password;

        $user = (new User())->setEmail($email)->setName($name)->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Admin %s created.', $email));
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
            ['Autumn Harvest Wreath', 'dried-bouquet', 'Dried autumn botanicals including protea, cotton, and seed pods on a natural base.', 1600, 0, null, $img('autumn-harvest-wreath')],
            ['Bloom Arrangement No. 10', 'bloom-box', self::GENERIC, 1400, 10, null, $img('bloom-arrangement-10')],
            ['Bloom Arrangement No. 11', 'others', self::GENERIC, 1400, 10, null, $img('bloom-arrangement-11')],
            ['Bloom Arrangement No. 12', 'glass-dome', self::GENERIC, 1400, 10, null, $img('bloom-arrangement-12')],
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

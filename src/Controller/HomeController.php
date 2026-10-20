<?php

namespace App\Controller;

use App\Service\ProductCatalog;
use App\Service\TestimonialProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const NAME_MAX_LENGTH = 80;
    private const MESSAGE_MAX_LENGTH = 1000;

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly TestimonialProvider $testimonials,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'products' => $this->catalog->findAll(),
            'categories' => $this->catalog->getCategories(),
            'active_category' => null,
            'testimonials' => $this->testimonials->findAll(),
        ]);
    }

    #[Route('/feedback', name: 'app_feedback', methods: ['POST'])]
    public function feedback(Request $request): RedirectResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $message = trim((string) $request->request->get('message', ''));

        if ('' === $name || '' === $message) {
            $this->addFlash('feedback_error', 'Please fill in your name and message.');
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH || mb_strlen($message) > self::MESSAGE_MAX_LENGTH) {
            $this->addFlash('feedback_error', 'Your name or message is too long.');
        } else {
            // TODO: persist the feedback once a storage layer exists.
            $this->addFlash('feedback_success', 'Thank you for sharing your experience!');
        }

        return $this->redirectToRoute('app_home', ['_fragment' => 'feedback']);
    }
}

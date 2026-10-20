<?php

namespace App\Controller;

use App\Service\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    public function __construct(private readonly ProductCatalog $catalog)
    {
    }

    #[Route('/catalog', name: 'app_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categories = $this->catalog->getCategories();
        $category = $request->query->getString('category');
        $active = isset($categories[$category]) ? $category : null;

        return $this->render('catalog/index.html.twig', [
            'products' => $this->catalog->findAll($active),
            'categories' => $categories,
            'active_category' => $active,
        ]);
    }
}

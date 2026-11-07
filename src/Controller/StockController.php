<?php

namespace App\Controller;

use App\Service\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Live stock for the storefront. Open pages poll this every few seconds, so it is public, touches
 * no session and answers 304 when nothing changed since the browser's last copy.
 */
final class StockController extends AbstractController
{
    public function __construct(private readonly ProductCatalog $catalog)
    {
    }

    #[Route('/stock', name: 'app_stock', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $response = new JsonResponse(['stock' => $this->catalog->stockSnapshot()]);
        $response->setEtag(md5((string) $response->getContent()));
        // no-cache = the browser may keep a copy but must revalidate it on every poll.
        $response->headers->set('Cache-Control', 'no-cache');
        $response->isNotModified($request);

        return $response;
    }
}

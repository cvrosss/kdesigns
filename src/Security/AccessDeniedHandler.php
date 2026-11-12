<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

/**
 * A signed-in user who opens a page their role does not allow gets the styled 403 page in every
 * environment (not only production), or a JSON 403 for fetch/XHR calls. Anonymous visitors never reach
 * this: LoginEntryPoint sends them to the login page instead.
 */
final class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return new Response(
            $this->twig->render('bundles/TwigBundle/Exception/error403.html.twig'),
            Response::HTTP_FORBIDDEN,
        );
    }
}

<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Anonymous visitors who open a protected page are sent to the login page; JSON/XHR callers get a 401.
 */
final class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'json') || str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            return new JsonResponse(['error' => 'Please log in to continue.'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urls->generate('app_login'));
    }
}

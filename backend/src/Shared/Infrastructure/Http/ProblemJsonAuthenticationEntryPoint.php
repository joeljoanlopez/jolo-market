<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Requests without a logged-in session get a 401 problem+json; the SPA then goes to /login.
 */
final readonly class ProblemJsonAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => Response::HTTP_UNAUTHORIZED],
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}

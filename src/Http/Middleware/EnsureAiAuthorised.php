<?php

declare(strict_types=1);

namespace Laradocs\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laradocs\Ai\ChatRegistry;
use Laradocs\Support\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides who may talk to the assistant.
 *
 * Three checks, in order, each one skipped when it is not configured: an auth
 * guard, a Gate ability, and whatever callback was registered with
 * `Laradocs::chatAuthorize()`. Nothing is configured by default, so the
 * endpoint is as open as the docs pages it sits on, which is the right default
 * for a public documentation site and the wrong one for an internal handbook.
 * The config block spells out both.
 *
 * A missing identity is a 401 and a refused one is a 403, so a client can tell
 * "log in" from "not for you".
 */
final class EnsureAiAuthorised
{
    public function __construct(private readonly ChatRegistry $registry) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Config::nullableString('laradocs.ai.auth.guard');
        $guarded = $guard !== null && $guard !== '';

        if ($guarded && ! Auth::guard($guard)->check()) {
            return $this->refuse(
                'Unauthenticated',
                'You must be signed in to use the documentation assistant.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $ability = Config::nullableString('laradocs.ai.auth.gate');
        $user = $guarded ? Auth::guard($guard)->user() : Auth::user();

        if ($ability !== null && $ability !== '' && Gate::forUser($user)->denies($ability)) {
            return $this->refuse(
                'Forbidden',
                'You are not allowed to use the documentation assistant.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (! $this->registry->authorises($request)) {
            return $this->refuse(
                'Forbidden',
                'You are not allowed to use the documentation assistant.',
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }

    private function refuse(string $error, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'message' => $message], $status);
    }
}

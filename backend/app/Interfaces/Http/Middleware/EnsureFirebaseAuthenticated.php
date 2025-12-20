<?php

namespace App\Interfaces\Http\Middleware;

use App\Application\Auth\FirebaseAuthService;
use Closure;
use Illuminate\Http\Request;

class EnsureFirebaseAuthenticated
{
    public function __construct(
        private readonly FirebaseAuthService $authService,
    ) {
    }

    public function handle(Request $request, Closure $next, ?string $role = null)
    {
        $token = $this->extractToken($request);

        abort_if(! $token, 401, 'Missing bearer token.');

        $user = $this->authService->authenticate($token);

        if ($role && ! $user->hasRole($role)) {
            abort(403, 'You do not have access to this resource.');
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        if ($request->bearerToken()) {
            return $request->bearerToken();
        }

        return $request->header('X-Firebase-Auth');
    }
}


<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->header('X-API-Key')
            ?? $this->extractBearerToken($request);

        if (!$providedKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Provide your API key via X-API-Key header or Authorization: Bearer <key>.',
            ], 401);
        }

        $user = User::where('api_key', $providedKey)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid API key.',
            ], 401);
        }

        // Inject the resolved user so controllers can use $request->user()
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}

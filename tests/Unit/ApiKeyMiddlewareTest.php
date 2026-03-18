<?php

namespace Tests\Unit;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiKeyMiddleware();
    }

    private function makeRequest(array $headers = []): Request
    {
        $request = Request::create('/api/v1/status', 'GET');
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }
        return $request;
    }

    // -------------------------------------------------------------------------
    // Missing key
    // -------------------------------------------------------------------------

    public function test_request_without_api_key_returns_401(): void
    {
        $request  = $this->makeRequest();
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_request_without_api_key_returns_error_message(): void
    {
        $request  = $this->makeRequest();
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));
        $data     = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsStringIgnoringCase('Unauthorized', $data['message']);
    }

    // -------------------------------------------------------------------------
    // Invalid key
    // -------------------------------------------------------------------------

    public function test_request_with_invalid_x_api_key_returns_401(): void
    {
        $request  = $this->makeRequest(['X-API-Key' => 'totally-wrong-key']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_request_with_invalid_bearer_token_returns_401(): void
    {
        $request  = $this->makeRequest(['Authorization' => 'Bearer invalid-token']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Valid key via X-API-Key header
    // -------------------------------------------------------------------------

    public function test_valid_x_api_key_allows_request(): void
    {
        $user    = User::factory()->create();
        $request = $this->makeRequest(['X-API-Key' => $user->api_key]);

        $resolvedUser = null;
        $this->middleware->handle($request, function ($req) use (&$resolvedUser) {
            $resolvedUser = $req->user();
            return response()->json(['ok' => true]);
        });

        $this->assertNotNull($resolvedUser);
        $this->assertEquals($user->id, $resolvedUser->id);
    }

    public function test_valid_x_api_key_returns_200(): void
    {
        $user    = User::factory()->create();
        $request = $this->makeRequest(['X-API-Key' => $user->api_key]);

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true], 200));

        $this->assertEquals(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Valid key via Authorization Bearer
    // -------------------------------------------------------------------------

    public function test_valid_bearer_token_allows_request(): void
    {
        $user    = User::factory()->create();
        $request = $this->makeRequest(['Authorization' => 'Bearer ' . $user->api_key]);

        $resolvedUser = null;
        $this->middleware->handle($request, function ($req) use (&$resolvedUser) {
            $resolvedUser = $req->user();
            return response()->json(['ok' => true]);
        });

        $this->assertNotNull($resolvedUser);
        $this->assertEquals($user->id, $resolvedUser->id);
    }

    public function test_bearer_without_bearer_prefix_returns_401(): void
    {
        $user    = User::factory()->create();
        $request = $this->makeRequest(['Authorization' => $user->api_key]);

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }
}

<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\Departement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class EnsurePasswordChangedMiddlewareTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_lets_guest_requests_through(): void
    {
        $middleware = new EnsurePasswordChanged();
        $request = Request::create('/api/budgets', 'GET');

        $response = $middleware->handle($request, fn ($req) => new Response('passed'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passed', $response->getContent());
    }

    public function test_lets_user_without_password_change_requirement_through(): void
    {
        $departement = Departement::first();
        $user = $this->createUser(['departement_id' => $departement->id, 'must_change_password' => false]);

        $middleware = new EnsurePasswordChanged();
        $request = Request::create('/api/budgets', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn ($req) => new Response('passed'));

        $this->assertSame('passed', $response->getContent());
    }

    public function test_blocks_a_general_route_when_password_must_be_changed(): void
    {
        $departement = Departement::first();
        $user = $this->createUser(['departement_id' => $departement->id, 'must_change_password' => true]);

        $middleware = new EnsurePasswordChanged();
        $request = Request::create('/api/budgets', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn ($req) => new Response('passed'));

        $this->assertSame(403, $response->getStatusCode());
    }

    #[DataProvider('exemptRoutesProvider')]
    public function test_allows_exempt_routes_when_password_must_be_changed(string $uri): void
    {
        $departement = Departement::first();
        $user = $this->createUser(['departement_id' => $departement->id, 'must_change_password' => true]);

        $middleware = new EnsurePasswordChanged();
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn ($req) => new Response('passed'));

        $this->assertSame('passed', $response->getContent());
    }

    public static function exemptRoutesProvider(): array
    {
        return [
            'me' => ['/api/me'],
            'change password' => ['/api/me/password'],
            'logout' => ['/api/logout'],
        ];
    }
}
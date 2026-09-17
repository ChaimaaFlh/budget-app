<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckPermission;
use App\Models\Departement;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class CheckPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_blocks_guest_requests(): void
    {
        $middleware = new CheckPermission();
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, fn ($req) => 'passed', 'budget.create');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('budget.create', $response->getContent());
    }

    public function test_blocks_user_without_the_required_permission(): void
    {
        $departement = Departement::first();
        $user = $this->createUser(['departement_id' => $departement->id]);

        $middleware = new CheckPermission();
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn ($req) => 'passed', 'budget.create');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_allows_user_with_the_required_permission(): void
    {
        $departement = Departement::first();
        $user = $this->createUser(['departement_id' => $departement->id]);
        $permission = Permission::where('code', 'budget.create')->first();
        $user->permissions()->attach($permission->id);

        $middleware = new CheckPermission();
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn ($req) => 'passed', 'budget.create');

        $this->assertSame('passed', $response);
    }
}
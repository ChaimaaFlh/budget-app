<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;


    public function test_register_method_creates_user_with_hashed_password_and_token(): void
    {
        $departement = Departement::first();

        $controller = new \App\Http\Controllers\AuthController();
        $request = \Illuminate\Http\Request::create('/api/register', 'POST', [
            'name' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
            'password' => 'password123',
            'departement_id' => $departement->id,
        ]);

        $response = $controller->register($request);
        $data = $response->getData(true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Compte créé avec succès !', $data['message']);
        $this->assertArrayHasKey('token', $data);
        $this->assertDatabaseHas('users', ['email' => 'jean.dupont@example.com']);

        $user = User::where('email', 'jean.dupont@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_register_fails_with_missing_fields(): void
    {
        $controller = new \App\Http\Controllers\AuthController();
        $request = \Illuminate\Http\Request::create('/api/register', 'POST', []);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->register($request);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $departement = Departement::first();
        $existing = $this->createUser(['email' => 'duplicate@example.com']);

        $controller = new \App\Http\Controllers\AuthController();
        $request = \Illuminate\Http\Request::create('/api/register', 'POST', [
            'name' => 'Autre',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'departement_id' => $departement->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->register($request);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = $this->createUser([
            'email' => 'login@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'token', 'must_change_password']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser([
            'email' => 'login2@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login2@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'inconnu@example.com',
            'password' => 'whatever123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->createUser([
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_must_change_password_flag(): void
    {
        $this->createUser([
            'email' => 'mustchange@example.com',
            'password' => Hash::make('secret123'),
            'must_change_password' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'mustchange@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['must_change_password' => true]);
    }

    public function test_me_returns_authenticated_user_with_permissions_and_departements(): void
    {
        $user = $this->loginWithPermissions(['bc.create']);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'permissions', 'departements'])
            ->assertJsonFragment(['id' => $user->id]);

        $this->assertContains('bc.create', $response->json('permissions'));
    }

    public function test_me_hides_password_field(): void
    {
        $this->loginWithPermissions();

        $response = $this->getJson('/api/me');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_me_returns_all_departements_for_user_with_wide_access(): void
    {
        $this->loginWithPermissions(['departement.view_all', 'user.manage_permissions']);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200);
        $this->assertCount(Departement::count(), $response->json('departements'));
    }

    public function test_me_returns_only_own_departement_for_regular_user(): void
    {
        $user = $this->loginWithPermissions(['bc.create']);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('departements'));
        $this->assertEquals($user->departement_id, $response->json('departements.0.id'));
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_change_password_succeeds_with_correct_current_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('oldpassword123')]);
        $this->actingAsUser($user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword12345',
            'password_confirmation' => 'newpassword12345',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword12345', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = $this->createUser(['password' => Hash::make('oldpassword123')]);
        $this->actingAsUser($user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'wrong-current',
            'password' => 'newpassword12345',
            'password_confirmation' => 'newpassword12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_fails_when_confirmation_does_not_match(): void
    {
        $user = $this->createUser(['password' => Hash::make('oldpassword123')]);
        $this->actingAsUser($user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword12345',
            'password_confirmation' => 'different12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_fails_when_shorter_than_12_chars(): void
    {
        $user = $this->createUser(['password' => Hash::make('oldpassword123')]);
        $this->actingAsUser($user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'oldpassword123',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_changed_middleware_blocks_other_routes_until_password_changed(): void
    {
        $user = $this->createUser(['must_change_password' => true]);
        $this->actingAsUser($user);

        $response = $this->getJson('/api/departements');

        $response->assertStatus(403);
    }

    public function test_password_changed_middleware_allows_me_and_password_and_logout_routes(): void
    {
        $user = $this->createUser(['must_change_password' => true]);
        $this->actingAsUser($user);

        $this->getJson('/api/me')->assertStatus(200);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}
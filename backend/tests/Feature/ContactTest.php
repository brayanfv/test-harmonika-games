<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_contact(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/contacts', [
                'name' => 'João da Silva',
                'email' => 'joao@example.com',
                'phone' => '48999999999',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('contacts', [
            'user_id' => $user->id,
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'phone' => '48999999999',
        ]);
    }

    public function test_authenticated_user_can_list_their_contacts(): void
    {
        $user = User::factory()->create();

        $user->contacts()->create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'phone' => '48999999999',
        ]);

        $user->contacts()->create([
            'name' => 'Maria da Silva',
            'email' => 'maria@example.com',
            'phone' => '48888888888',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/contacts');

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_user_cannot_access_another_users_contact(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $contact = $anotherUser->contacts()->create([
            'name' => 'Contato de outro usuário',
            'email' => 'outro@example.com',
            'phone' => '48999999999',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/contacts/{$contact->id}");

        $response->assertNotFound();
    }

    public function test_authenticated_user_can_view_their_contact(): void
    {
        $user = User::factory()->create();

        $contact = $user->contacts()->create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'phone' => '48999999999',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/contacts/{$contact->id}");

        $response
            ->assertOk()
            ->assertJson([
                'id' => $contact->id,
                'name' => 'João da Silva',
                'email' => 'joao@example.com',
                'phone' => '48999999999',
            ]);
    }

    public function test_authenticated_user_can_update_their_contact(): void
    {
        $user = User::factory()->create();

        $contact = $user->contacts()->create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'phone' => '48999999999',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/contacts/{$contact->id}", [
                'name' => 'João Silva Atualizado',
                'email' => 'joao.atualizado@example.com',
                'phone' => '48888888888',
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $contact->id,
                'name' => 'João Silva Atualizado',
                'email' => 'joao.atualizado@example.com',
                'phone' => '48888888888',
            ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'user_id' => $user->id,
            'name' => 'João Silva Atualizado',
            'email' => 'joao.atualizado@example.com',
            'phone' => '48888888888',
        ]);
    }

    public function test_authenticated_user_can_delete_their_contact(): void
    {
        $user = User::factory()->create();

        $contact = $user->contacts()->create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
            'phone' => '48999999999',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/contacts/{$contact->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_user_cannot_update_another_users_contact(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $contact = $anotherUser->contacts()->create([
            'name' => 'Contato de outro usuário',
            'email' => 'outro@example.com',
            'phone' => '48999999999',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/contacts/{$contact->id}", [
                'name' => 'Tentativa de alteração',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Contato de outro usuário',
        ]);
    }

    public function test_user_cannot_delete_another_users_contact(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $contact = $anotherUser->contacts()->create([
            'name' => 'Contato de outro usuário',
            'email' => 'outro@example.com',
            'phone' => '48999999999',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/contacts/{$contact->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_guest_cannot_access_contacts(): void
    {
        $response = $this->getJson('/api/contacts');

        $response->assertUnauthorized();
    }

    public function test_contact_creation_requires_a_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/contacts', [
                'email' => 'joao@example.com',
                'phone' => '48999999999',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('contacts', 0);
    }
}

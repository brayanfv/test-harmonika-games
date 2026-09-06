<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_payable_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'type' => 'payable',
                'description' => 'Conta de energia',
                'amount' => 250.50,
                'due_date' => '2026-09-15',
            ]);

        $response
            ->assertCreated()
            ->assertJson([
                'type' => 'payable',
                'description' => 'Conta de energia',
                'amount' => '250.50',
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('financial_transactions', [
            'user_id' => $user->id,
            'type' => 'payable',
            'description' => 'Conta de energia',
            'amount' => 250.50,
            'status' => 'pending',
        ]);
    }

    public function test_authenticated_user_can_create_a_receivable_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'type' => 'receivable',
                'description' => 'Serviço de desenvolvimento',
                'amount' => 1500.00,
                'due_date' => '2026-09-20',
            ]);

        $response
            ->assertCreated()
            ->assertJson([
                'type' => 'receivable',
                'description' => 'Serviço de desenvolvimento',
                'amount' => '1500.00',
                'status' => 'pending',
            ]);
    }

    public function test_authenticated_user_can_list_their_transactions(): void
    {
        $user = User::factory()->create();

        $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta de energia',
            'amount' => 250.00,
            'due_date' => '2026-09-15',
        ]);

        $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Serviço',
            'amount' => 1000.00,
            'due_date' => '2026-09-20',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/transactions');

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_user_cannot_access_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $transaction = $anotherUser->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta de outro usuário',
            'amount' => 500.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/transactions/{$transaction->id}");

        $response->assertNotFound();
    }

    public function test_authenticated_user_can_view_their_transaction(): void
    {
        $user = User::factory()->create();

        $transaction = $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Serviço de desenvolvimento',
            'amount' => 1500.00,
            'due_date' => '2026-09-20',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/transactions/{$transaction->id}");

        $response
            ->assertOk()
            ->assertJson([
                'id' => $transaction->id,
                'type' => 'receivable',
                'description' => 'Serviço de desenvolvimento',
                'amount' => '1500.00',
            ]);
    }

    public function test_authenticated_user_can_update_their_transaction(): void
    {
        $user = User::factory()->create();

        $transaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta antiga',
            'amount' => 200.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/transactions/{$transaction->id}", [
                'description' => 'Conta atualizada',
                'amount' => 350.00,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $transaction->id,
                'description' => 'Conta atualizada',
                'amount' => '350.00',
            ]);

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
            'description' => 'Conta atualizada',
            'amount' => 350.00,
        ]);
    }

    public function test_authenticated_user_can_delete_their_transaction(): void
    {
        $user = User::factory()->create();

        $transaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta para excluir',
            'amount' => 100.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('financial_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_user_cannot_update_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $transaction = $anotherUser->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta protegida',
            'amount' => 500.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/transactions/{$transaction->id}", [
                'description' => 'Tentativa de alteração',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'description' => 'Conta protegida',
        ]);
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $transaction = $anotherUser->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta protegida',
            'amount' => 500.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_guest_cannot_access_transactions(): void
    {
        $response = $this->getJson('/api/transactions');

        $response->assertUnauthorized();
    }

    public function test_transaction_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'type' => 'invalid',
                'description' => '',
                'amount' => 0,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'description',
                'amount',
                'due_date',
            ]);

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_user_can_filter_transactions_by_type(): void
    {
        $user = User::factory()->create();

        $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta a pagar',
            'amount' => 200.00,
            'due_date' => '2026-09-15',
        ]);

        $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Conta a receber',
            'amount' => 1000.00,
            'due_date' => '2026-09-20',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/transactions?type=payable');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'description' => 'Conta a pagar',
            ]);
    }

    public function test_user_can_filter_transactions_by_status(): void
    {
        $user = User::factory()->create();

        $pendingTransaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta pendente',
            'amount' => 200.00,
            'due_date' => '2026-09-15',
        ]);

        $user->financialTransactions()->create([
            'type' => 'receivable',
            'description' => 'Conta liquidada',
            'amount' => 1000.00,
            'due_date' => '2026-09-20',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/transactions?status=paid');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'description' => 'Conta liquidada',
            ]);
    }

    public function test_authenticated_user_can_pay_a_pending_transaction(): void
    {
        $user = User::factory()->create();

        $transaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta para liquidar',
            'amount' => 300.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/transactions/{$transaction->id}/pay");

        $response
            ->assertOk()
            ->assertJson([
                'id' => $transaction->id,
                'status' => 'paid',
            ]);

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);

        $this->assertNotNull(
            $transaction->fresh()->paid_at
        );
    }

    public function test_user_cannot_pay_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $transaction = $anotherUser->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta de outro usuário',
            'amount' => 500.00,
            'due_date' => '2026-09-15',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/transactions/{$transaction->id}/pay");

        $response->assertNotFound();

        $this->assertDatabaseHas('financial_transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);
    }

    public function test_paid_transaction_cannot_be_paid_again(): void
    {
        $user = User::factory()->create();

        $transaction = $user->financialTransactions()->create([
            'type' => 'payable',
            'description' => 'Conta já liquidada',
            'amount' => 300.00,
            'due_date' => '2026-09-15',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/transactions/{$transaction->id}/pay");

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Transaction is already paid.',
            ]);
    }

    public function test_transaction_can_be_created_with_a_contact(): void
    {
        $user = User::factory()->create();

        $contact = $user->contacts()->create([
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'contact_id' => $contact->id,
                'type' => 'receivable',
                'description' => 'Serviço prestado',
                'amount' => 1500.00,
                'due_date' => '2026-09-20',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('contact.id', $contact->id);
    }

    public function test_transaction_cannot_use_another_users_contact(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $contact = $anotherUser->contacts()->create([
            'name' => 'Contato de outro usuário',
            'email' => 'outro@example.com',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'contact_id' => $contact->id,
                'type' => 'receivable',
                'description' => 'Tentativa inválida',
                'amount' => 1500.00,
                'due_date' => '2026-09-20',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id']);

        $this->assertDatabaseCount('financial_transactions', 0);
    }
}
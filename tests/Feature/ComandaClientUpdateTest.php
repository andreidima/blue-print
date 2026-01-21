<?php

namespace Tests\Feature;

use App\Enums\StatusComanda;
use App\Enums\SursaComanda;
use App\Enums\TipComanda;
use App\Models\Client;
use App\Models\Comanda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandaClientUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_change_client_for_an_order(): void
    {
        $user = User::factory()->create(['activ' => 1]);
        $clientA = Client::create(['nume' => 'Client A']);
        $clientB = Client::create(['nume' => 'Client B']);

        $comanda = Comanda::create([
            'client_id' => $clientA->id,
            'tip' => TipComanda::ComandaFerma->value,
            'sursa' => SursaComanda::Fizic->value,
            'status' => StatusComanda::Nou->value,
            'timp_estimat_livrare' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('comenzi.update', $comanda), [
                'client_id' => $clientB->id,
                'status' => StatusComanda::Nou->value,
                'timp_estimat_livrare' => now()->toDateTimeString(),
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('comenzi', [
            'id' => $comanda->id,
            'client_id' => $clientB->id,
        ]);
    }

    public function test_it_allows_updates_without_client_id_when_missing_from_request(): void
    {
        $user = User::factory()->create(['activ' => 1]);
        $client = Client::create(['nume' => 'Client']);

        $comanda = Comanda::create([
            'client_id' => $client->id,
            'tip' => TipComanda::ComandaFerma->value,
            'sursa' => SursaComanda::Fizic->value,
            'status' => StatusComanda::Nou->value,
            'timp_estimat_livrare' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('comenzi.update', $comanda), [
                'status' => StatusComanda::InVerificare->value,
                'timp_estimat_livrare' => now()->toDateTimeString(),
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('comenzi', [
            'id' => $comanda->id,
            'client_id' => $client->id,
            'status' => StatusComanda::InVerificare->value,
        ]);
    }
}


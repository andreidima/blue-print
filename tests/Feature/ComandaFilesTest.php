<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Comanda;
use App\Models\ComandaAtasament;
use App\Models\Mockup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComandaFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_view_download_and_delete_atasamente(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['activ' => 1]);
        $client = Client::create(['nume' => 'Test', 'prenume' => 'Client']);
        $comanda = Comanda::create([
            'client_id' => $client->id,
            'tip' => 'test',
            'sursa' => 'test',
            'status' => 'test',
            'timp_estimat_livrare' => now(),
        ]);

        $path = 'comenzi/'.$comanda->id.'/atasamente/test.txt';
        Storage::disk('public')->put($path, 'hello');

        $atasament = ComandaAtasament::create([
            'comanda_id' => $comanda->id,
            'uploaded_by' => $user->id,
            'original_name' => 'test.txt',
            'path' => $path,
            'mime' => 'text/plain',
            'size' => 5,
        ]);

        $this->actingAs($user);

        $this->get($atasament->fileUrl())
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->get($atasament->downloadUrl())
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->delete($atasament->destroyUrl())
            ->assertStatus(302);

        $this->assertDatabaseMissing('comanda_atasamente', ['id' => $atasament->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_it_404s_when_atasament_does_not_belong_to_comanda(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['activ' => 1]);
        $client = Client::create(['nume' => 'Test', 'prenume' => 'Client']);

        $comanda = Comanda::create([
            'client_id' => $client->id,
            'tip' => 'test',
            'sursa' => 'test',
            'status' => 'test',
            'timp_estimat_livrare' => now(),
        ]);

        $otherComanda = Comanda::create([
            'client_id' => $client->id,
            'tip' => 'test',
            'sursa' => 'test',
            'status' => 'test',
            'timp_estimat_livrare' => now(),
        ]);

        $path = 'comenzi/'.$comanda->id.'/atasamente/test.txt';
        Storage::disk('public')->put($path, 'hello');

        $atasament = ComandaAtasament::create([
            'comanda_id' => $comanda->id,
            'uploaded_by' => $user->id,
            'original_name' => 'test.txt',
            'path' => $path,
            'mime' => 'text/plain',
            'size' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('comenzi.atasamente.view', ['comanda' => $otherComanda->id, 'atasament' => $atasament->id]))
            ->assertNotFound();
    }

    public function test_it_can_view_download_and_delete_mockupuri(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['activ' => 1]);
        $client = Client::create(['nume' => 'Test', 'prenume' => 'Client']);
        $comanda = Comanda::create([
            'client_id' => $client->id,
            'tip' => 'test',
            'sursa' => 'test',
            'status' => 'test',
            'timp_estimat_livrare' => now(),
        ]);

        $path = 'comenzi/'.$comanda->id.'/mockupuri/mock.txt';
        Storage::disk('public')->put($path, 'hello');

        $mockup = Mockup::create([
            'comanda_id' => $comanda->id,
            'uploaded_by' => $user->id,
            'original_name' => 'mock.txt',
            'path' => $path,
            'mime' => 'text/plain',
            'size' => 5,
            'comentariu' => null,
        ]);

        $this->actingAs($user);

        $this->get($mockup->fileUrl())
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->get($mockup->downloadUrl())
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->delete($mockup->destroyUrl())
            ->assertStatus(302);

        $this->assertDatabaseMissing('mockupuri', ['id' => $mockup->id]);
        Storage::disk('public')->assertMissing($path);
    }
}

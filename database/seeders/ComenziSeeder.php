<?php

namespace Database\Seeders;

use App\Enums\MetodaPlata;
use App\Enums\StatusComanda;
use App\Enums\TipComanda;
use App\Enums\SursaComanda;
use App\Models\Client;
use App\Models\Comanda;
use App\Models\ComandaProdus;
use App\Models\Plata;
use App\Models\Produs;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ComenziSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Client::count() === 0 || Produs::count() === 0) {
            $this->call(ClientiProduseSeeder::class);
        }

        $faker = Faker::create('ro_RO');
        $clientIds = Client::pluck('id')->all();
        $produse = Produs::where('activ', true)->get();
        if ($produse->isEmpty()) {
            $produse = Produs::all();
        }

        $userIds = User::pluck('id')->all();

        if (empty($clientIds) || $produse->isEmpty()) {
            return;
        }

        $tipuri = array_keys(TipComanda::options());
        $surse = array_keys(SursaComanda::options());
        $statusuri = array_keys(StatusComanda::options());
        $metodePlata = array_keys(MetodaPlata::options());

        $randomUser = function (int $chance = 40) use ($faker, $userIds): ?int {
            if (empty($userIds) || !$faker->boolean($chance)) {
                return null;
            }

            return $faker->randomElement($userIds);
        };

        for ($i = 0; $i < 100; $i++) {
            $status = $faker->randomElement($statusuri);
            $timpLivrare = Carbon::now()
                ->addDays($faker->numberBetween(-5, 12))
                ->setTime($faker->numberBetween(8, 19), $faker->randomElement([0, 15, 30, 45]));

            $finalizatLa = null;
            if (in_array($status, StatusComanda::finalStates(), true)) {
                $finalizatLa = (clone $timpLivrare)->addHours($faker->numberBetween(-4, 12));
            }

            $comanda = Comanda::create([
                'client_id' => $faker->randomElement($clientIds),
                'tip' => $faker->randomElement($tipuri),
                'sursa' => $faker->randomElement($surse),
                'status' => $status,
                'timp_estimat_livrare' => $timpLivrare,
                'finalizat_la' => $finalizatLa,
                'necesita_tipar_exemplu' => $faker->boolean(20),
                'frontdesk_user_id' => $randomUser(),
                'supervizor_user_id' => $randomUser(30),
                'grafician_user_id' => $randomUser(30),
                'executant_user_id' => $randomUser(30),
                'nota_frontdesk' => $faker->boolean(20) ? $faker->sentence() : null,
                'nota_grafician' => $faker->boolean(20) ? $faker->sentence() : null,
                'nota_executant' => $faker->boolean(20) ? $faker->sentence() : null,
            ]);

            $linieCount = $faker->numberBetween(1, 3);
            $produseSelectate = $produse->random(min($linieCount, $produse->count()));
            foreach ($produseSelectate as $produs) {
                $cantitate = $faker->numberBetween(1, 25);
                $totalLinie = round($produs->pret * $cantitate, 2);

                ComandaProdus::create([
                    'comanda_id' => $comanda->id,
                    'produs_id' => $produs->id,
                    'cantitate' => $cantitate,
                    'pret_unitar' => $produs->pret,
                    'total_linie' => $totalLinie,
                ]);
            }

            $total = $comanda->produse()->sum('total_linie');
            if ($total > 0 && $faker->boolean(60)) {
                $suma = $total;
                if ($faker->boolean(35)) {
                    $suma = round($total * $faker->randomFloat(2, 0.2, 0.9), 2);
                }

                Plata::create([
                    'comanda_id' => $comanda->id,
                    'suma' => $suma,
                    'metoda' => $faker->randomElement($metodePlata),
                    'numar_factura' => $faker->boolean(30) ? $faker->bothify('INV-####') : null,
                    'platit_la' => (clone $timpLivrare)->subHours($faker->numberBetween(1, 72)),
                    'note' => $faker->boolean(20) ? $faker->sentence() : null,
                    'created_by' => $randomUser(70),
                ]);
            }

            $comanda->recalculateTotals();
        }
    }
}

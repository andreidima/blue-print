<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Produs;
use Illuminate\Database\Seeder;

class ClientiProduseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::insert([
            [
                'type' => 'pf',
                'nume' => 'Popescu Andrei',
                'adresa' => 'Str. Unirii 10, Bucuresti',
                'telefon' => '0722000001',
                'email' => 'andrei.popescu@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'pf',
                'nume' => 'Ionescu Maria',
                'adresa' => 'Bd. Libertatii 25, Cluj',
                'telefon' => '0722000002',
                'email' => 'maria.ionescu@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'pf',
                'nume' => 'Stan George',
                'adresa' => 'Str. Lalelelor 5, Timisoara',
                'telefon' => '0722000003',
                'email' => 'george.stan@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Produs::insert([
            [
                'denumire' => 'Flyer A5 color',
                'pret' => 2.50,
                'activ' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'denumire' => 'Carte de vizita',
                'pret' => 1.20,
                'activ' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'denumire' => 'Banner indoor',
                'pret' => 35.00,
                'activ' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'denumire' => 'Autocolant personalizat',
                'pret' => 8.75,
                'activ' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'denumire' => 'Plicuri personalizate',
                'pret' => 3.40,
                'activ' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

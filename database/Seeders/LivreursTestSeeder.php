<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Livreur;
use App\Models\DemandeAdhesion;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LivreursTestSeeder extends Seeder
{
    /**
     * Table de correspondance code wilaya -> nom
     */
    private $wilayas = [
        '16' => 'Alger',
        '31' => 'Oran',
        '35' => 'Boumerdès',
        '42' => 'Tipaza',
        '09' => 'Blida',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Création des livreurs de test...');

        // Créer 3 livreurs pour Alger (wilaya 16)
        for ($i = 1; $i <= 3; $i++) {
            $this->createLivreur(
                nom: 'Livreur',
                prenom: "Alger $i",
                email: "livreur.alger$i@example.com",
                telephone: "0551{$i}00001",
                wilaya_id: '16',
                type: $i % 2 === 0 ? 'distributeur' : 'ramasseur'
            );
        }

        // Créer 2 livreurs pour Oran (wilaya 31)
        for ($i = 1; $i <= 2; $i++) {
            $this->createLivreur(
                nom: 'Livreur',
                prenom: "Oran $i",
                email: "livreur.oran$i@example.com",
                telephone: "0552{$i}00001",
                wilaya_id: '31',
                type: 'distributeur'
            );
        }

        // Créer 2 livreurs pour Boumerdès (wilaya 35)
        for ($i = 1; $i <= 2; $i++) {
            $this->createLivreur(
                nom: 'Livreur',
                prenom: "Boumerdès $i",
                email: "livreur.boumerdes$i@example.com",
                telephone: "0553{$i}00001",
                wilaya_id: '35',
                type: $i === 1 ? 'distributeur' : 'ramasseur'
            );
        }

        // Créer 1 livreur pour Tipaza (wilaya 42)
        $this->createLivreur(
            nom: 'Livreur',
            prenom: 'Tipaza',
            email: 'livreur.tipaza@example.com',
            telephone: '0554400001',
            wilaya_id: '42',
            type: 'distributeur'
        );

        // Créer 1 livreur pour Blida (wilaya 09)
        $this->createLivreur(
            nom: 'Livreur',
            prenom: 'Blida',
            email: 'livreur.blida@example.com',
            telephone: '0555500001',
            wilaya_id: '09',
            type: 'ramasseur'
        );

        $this->command->info('✅ ' . Livreur::count() . ' livreurs au total créés');
        $this->command->info('Répartition par wilaya :');
        foreach ($this->wilayas as $code => $nom) {
            $count = Livreur::where('wilaya_id', $code)->count();
            $this->command->info("   - $code ($nom) : $count livreur(s)");
        }
    }

    /**
     * Créer un livreur avec son utilisateur associé
     */
    private function createLivreur(
        string $nom,
        string $prenom,
        string $email,
        string $telephone,
        string $wilaya_id,
        string $type = 'distributeur'
    ): void {
        // Créer l'utilisateur
        $user = User::create([
            'id' => (string) Str::uuid(),
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => Hash::make('password'),
            'telephone' => $telephone,
            'role' => 'livreur',
            'actif' => true,
        ]);

        // Créer une demande d'adhésion approuvée
        $demande = DemandeAdhesion::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'vehicule_type' => 'moto',
            'id_card_type' => 'CIN',
            'id_card_number' => 'CIN-' . rand(100000, 999999),
            'status' => 'approved',
            'message' => 'Demande approuvée',
            'date' => now(),
        ]);

        // Créer le livreur avec wilaya_id
        Livreur::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'demande_adhesions_id' => $demande->id,
            'type' => $type,
            'desactiver' => false,
            'wilaya_id' => $wilaya_id,
        ]);

        $this->command->info("   ✓ Livreur $prenom créé (wilaya $wilaya_id)");
    }
}
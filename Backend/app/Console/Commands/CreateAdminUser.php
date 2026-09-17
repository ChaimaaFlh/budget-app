<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Departement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin {--force : Autorise la création même si des utilisateurs existent déjà}';

    protected $description = 'Crée le premier administrateur de l’application';

    public function handle(): int
    {
        if (User::exists() && !$this->option('force')) {
            $this->error('Des utilisateurs existent déjà. Utilisez --force uniquement si vous voulez ajouter un administrateur.');

            return self::FAILURE;
        }

        $permissions = Permission::pluck('id');
        if ($permissions->isEmpty()) {
            $this->error('Aucune permission disponible. Exécutez d’abord les migrations.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nom de l’administrateur'));
        $email = strtolower(trim((string) $this->ask('Adresse e-mail')));
        $password = (string) $this->secret('Mot de passe (12 caractères minimum)');
        $confirmation = (string) $this->secret('Confirmez le mot de passe');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Le nom et une adresse e-mail valide sont obligatoires.');

            return self::FAILURE;
        }

        if (strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Les mots de passe doivent être identiques et contenir au moins 12 caractères.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Cette adresse e-mail est déjà utilisée.');

            return self::FAILURE;
        }

        $departement = $this->choisirDepartement();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'departement_id' => $departement->id,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->permissions()->sync($permissions);

        $this->info("Administrateur créé : {$user->email}");
        $this->line('Le mot de passe devra être changé à la première connexion.');

        return self::SUCCESS;
    }

    private function choisirDepartement(): Departement
    {
        $departements = Departement::orderBy('nom')->get();

        if ($departements->isEmpty()) {
            $nom = trim((string) $this->ask('Nom du premier département', 'Administration'));

            return Departement::create(['nom' => $nom !== '' ? $nom : 'Administration']);
        }

        $this->table(['ID', 'Département'], $departements->map(fn (Departement $departement) => [
            $departement->id,
            $departement->nom,
        ])->all());

        do {
            $id = (int) $this->ask('ID du département de rattachement', $departements->first()->id);
            $departement = $departements->firstWhere('id', $id);
            if ($departement === null) {
                $this->error('Département introuvable. Réessayez.');
            }
        } while ($departement === null);

        return $departement;
    }
}

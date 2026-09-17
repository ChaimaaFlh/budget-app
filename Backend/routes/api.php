<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\LigneBudgetController;
use App\Http\Controllers\LigneBudgetAnnuiteController;
use App\Http\Controllers\BonCommandeController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\SousCategorieController;
use App\Http\Controllers\DepartementController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FactureDocumentController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\PermissionPackController;
use App\Http\Controllers\StatutPersonnaliseController;
use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\LigneBudget;
use App\Models\Facture;
use App\Support\ReferenceGenerator;

// Seule route publique : les comptes sont créés par un administrateur.
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par authentification
Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {

    Route::get('/references/next/{type}', function (string $type) {
        $types = [
            'budget' => [Budget::class, 'code', 'BUD'],
            'ligne' => [LigneBudget::class, 'code', 'LB'],
            'bon-commande' => [BonCommande::class, 'numero_bc', 'BC'],
            'facture' => [Facture::class, 'ref_facture', 'FAC'],
        ];
        abort_unless(isset($types[$type]), 404);
        [$model, $column, $prefix] = $types[$type];
        return response()->json(['reference' => ReferenceGenerator::make($model, $column, $prefix)]);
    });

    // Profil & Déconnexion
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);

    // Départements
    Route::apiResource('departements', DepartementController::class)
        ->middleware('permission:departement.view_all');

    // Budgets
    Route::apiResource('budgets', BudgetController::class)
        ->middlewareFor(['store'], 'permission:budget.create')
        ->middlewareFor(['update'], 'permission:budget.edit')
        ->middlewareFor(['destroy'], 'permission:budget.delete');
    Route::post('/budgets/{budget}/close', [BudgetController::class, 'close'])
        ->middleware('permission:budget.close');

    // Lignes Budgétaires & Annuités
    Route::apiResource('ligne-budgets', LigneBudgetController::class)
        ->middlewareFor(['store'], 'permission:ligne.create')
        ->middlewareFor(['update'], 'permission:ligne.edit')
        ->middlewareFor(['destroy'], 'permission:ligne.delete');
    Route::get('/ligne-budgets/{ligneBudget}/annuites', [LigneBudgetAnnuiteController::class, 'index']);
    Route::put('/ligne-budgets/{ligneBudget}/annuites', [LigneBudgetAnnuiteController::class, 'replaceAll'])->middleware('permission:ligne.edit');
    Route::post('/ligne-budgets/{ligneBudget}/annuites', [LigneBudgetAnnuiteController::class, 'store'])->middleware('permission:ligne.edit');
    Route::put('/ligne-budgets/{ligneBudget}/annuites/{annuite}', [LigneBudgetAnnuiteController::class, 'update'])->middleware('permission:ligne.edit');
    Route::delete('/ligne-budgets/{ligneBudget}/annuites/{annuite}', [LigneBudgetAnnuiteController::class, 'destroy'])->middleware('permission:ligne.edit');

    // Bons de Commande & Factures
    Route::apiResource('bons-commande', BonCommandeController::class)
        ->middlewareFor(['store'], 'permission:bc.create')
        ->middlewareFor(['update'], 'permission:bc.edit')
        ->middlewareFor(['destroy'], 'permission:bc.delete');
    Route::post('/bons-commande/{bonCommande}/validate', [BonCommandeController::class, 'validateBc'])
        ->middleware('permission:bc.validate');
    Route::post('/bons-commande/{bonCommande}/unvalidate', [BonCommandeController::class, 'unvalidateBc'])
        ->middleware('permission:bc.unvalidate');
    
    Route::get('/bons-commande/{bonCommande}/factures', [FactureController::class, 'index']);
    Route::post('/bons-commande/{bonCommande}/factures', [FactureController::class, 'store'])->middleware('permission:facture.create');
    Route::get('/bons-commande/{bonCommande}/factures/{facture}', [FactureController::class, 'show']);
    Route::put('/bons-commande/{bonCommande}/factures/{facture}', [FactureController::class, 'update'])->middleware('permission:facture.edit');
    Route::delete('/bons-commande/{bonCommande}/factures/{facture}', [FactureController::class, 'destroy'])->middleware('permission:facture.delete');
    Route::get('/statuts-personnalises', [StatutPersonnaliseController::class, 'index']);
    Route::post('/statuts-personnalises', [StatutPersonnaliseController::class, 'store']);
    Route::delete('/statuts-personnalises/{statutPersonnalise}', [StatutPersonnaliseController::class, 'destroy']);

    // Documents liés à une facture (scan + pièces justificatives, 5 max, 10 Mo/fichier)
    Route::get('/bons-commande/{bonCommande}/factures/{facture}/documents', [FactureDocumentController::class, 'index']);
    Route::post('/bons-commande/{bonCommande}/factures/{facture}/documents', [FactureDocumentController::class, 'store'])->middleware('permission:facture.create');
    Route::get('/bons-commande/{bonCommande}/factures/{facture}/documents/{document}/download', [FactureDocumentController::class, 'download']);
    Route::delete('/bons-commande/{bonCommande}/factures/{facture}/documents/{document}', [FactureDocumentController::class, 'destroy'])->middleware('permission:facture.create');

    // Catégories
    Route::apiResource('categories', CategorieController::class)
        ->middlewareFor(['store'], 'permission:categorie.create')
        ->middlewareFor(['update'], 'permission:categorie.edit')
        ->middlewareFor(['destroy'], 'permission:categorie.delete');
    Route::apiResource('sous-categories', SousCategorieController::class)
        ->middlewareFor(['store'], 'permission:souscategorie.create')
        ->middlewareFor(['update'], 'permission:souscategorie.edit')
        ->middlewareFor(['destroy'], 'permission:souscategorie.delete');

    // Gestion des Permissions & Assignations aux Utilisateurs
    Route::middleware('permission:user.manage_permissions')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/users/{id}/permissions', [PermissionController::class, 'userPermissions']);
        Route::put('/users/{id}/permissions', [PermissionController::class, 'updateUserPermissions']);
        Route::get('/permission-packs', [PermissionPackController::class, 'index']);
        Route::post('/permission-packs', [PermissionPackController::class, 'store']);
        Route::post('/permission-packs/{permissionPack}/apply/{userId}', [PermissionPackController::class, 'apply']);
    });
    Route::apiResource('fournisseurs', FournisseurController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middlewareFor(['store'], 'permission:fournisseur.create')
        ->middlewareFor(['update'], 'permission:fournisseur.edit')
        ->middlewareFor(['destroy'], 'permission:fournisseur.delete');
});
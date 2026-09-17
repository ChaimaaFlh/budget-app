<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]


class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $fillable = [
        'name', 'email', 'password', 'departement_id', 'is_active', 'must_change_password',
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function hasPermission(string $code): bool
    {
        return $this->permissions()->where('code', $code)->exists();
    }

    public function hasDepartmentWideAccess(): bool
    {
        return $this->hasPermission('departement.view_all')
            && $this->hasPermission('user.manage_permissions');
    }

    public function bons_commande()
    {
        return $this->hasMany(BonCommande::class, 'user_id');
    }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PermissionPack extends Model { protected $fillable = ['nom','description']; public function permissions() { return $this->belongsToMany(Permission::class, 'permission_pack_permission'); } }

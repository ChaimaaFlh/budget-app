<?php
namespace App\Http\Controllers;
use App\Models\PermissionPack; use Illuminate\Http\Request;
class PermissionPackController extends Controller {
 public function index(){ return PermissionPack::with('permissions')->orderBy('nom')->get(); }
 public function store(Request $r){$v=$r->validate(['nom'=>'required|string|unique:permission_packs,nom','description'=>'nullable|string','permission_ids'=>'required|array','permission_ids.*'=>'exists:permissions,id']); $p=PermissionPack::create($v);$p->permissions()->sync($v['permission_ids']);return $p->load('permissions');}
 public function apply(Request $r, PermissionPack $permissionPack, $userId){$v=$r->validate(['replace'=>'boolean']); $user=\App\Models\User::findOrFail($userId); $v['replace'] ?? true ? $user->permissions()->sync($permissionPack->permissions->pluck('id')) : $user->permissions()->syncWithoutDetaching($permissionPack->permissions->pluck('id')); return $user->load('permissions');}
}

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  $packs=[
   'Chef de projet'=>['ligne.create','ligne.edit','bc.create','bc.edit','bc.validate','facture.create','facture.edit'],
   'Directeur'=>['budget.create','budget.edit','budget.close','ligne.create','ligne.edit','bc.create','bc.edit','bc.validate','facture.create','facture.edit','departement.view_all'],
   'Lecteur'=>[],
  ];
  foreach($packs as $nom=>$codes){$id=DB::table('permission_packs')->updateOrInsert(['nom'=>$nom],['description'=>"Pack {$nom}",'updated_at'=>now(),'created_at'=>now()]);$packId=DB::table('permission_packs')->where('nom',$nom)->value('id');$ids=DB::table('permissions')->whereIn('code',$codes)->pluck('id'); foreach($ids as $permissionId) DB::table('permission_pack_permission')->updateOrInsert(['permission_pack_id'=>$packId,'permission_id'=>$permissionId],[]);}
 }
 public function down(): void { DB::table('permission_packs')->whereIn('nom',['Chef de projet','Directeur','Lecteur'])->delete(); }
};

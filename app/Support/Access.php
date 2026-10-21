<?php
namespace App\Support;
use App\Models\User;
class Access {
 public static function require(User $u,string $a): void {abort_unless($u->canDo($a),403,'Permission required: '.$a);}
 public static function branch(User $u,int $id): void {abort_unless($u->hasBranch($id),403,'This branch is not assigned to you.');}
 public static function scope($query,User $u,string $column='branch_id') {return $u->role==='admin'?$query:$query->whereIn($column,$u->branch_ids??[]);}
}

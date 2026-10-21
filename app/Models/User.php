<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
 protected $attributes = ['active'=>true,'role'=>'staff','discount_limit_bps'=>0];
 protected $guarded = ['id'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array { return ['permissions'=>'array','branch_ids'=>'array','active'=>'boolean','password'=>'hashed']; }
 public function canDo(string $ability): bool {return $this->active && ($this->role === 'admin' || in_array($ability,$this->permissions ?? [],true));}
 public function hasBranch(int $id): bool {return $this->role==='admin' || in_array($id,array_map('intval',$this->branch_ids??[]),true);}
}

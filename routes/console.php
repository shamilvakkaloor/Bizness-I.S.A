<?php
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
Artisan::command('business:admin',function(){
 if(User::where('role','admin')->exists()) {$this->error('An admin already exists. Use business:reset-password if needed.');return 1;}
 $name=$this->ask('Admin name');$email=$this->ask('Admin email');$password=$this->secret('Password (at least 12 characters)');
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||!$name){$this->error('Invalid input.');return 1;}
 User::create(['name'=>$name,'email'=>$email,'password'=>$password,'role'=>'admin','permissions'=>[],'branch_ids'=>[]]);$this->info('Admin created.');
});
Artisan::command('business:reset-password {email}',function(){
 $u=User::where('email',$this->argument('email'))->firstOrFail();$p=$this->secret('New password (at least 12 characters)');if(strlen($p)<12){$this->error('Password too short.');return 1;}$u->password=$p;$u->save();\App\Support\Audit::record(null,'password_reset','users',$u->id,null,null,'Console reset');$this->info('Password changed.');
});

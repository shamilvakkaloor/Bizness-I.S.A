<?php
if ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals('SETUP_TOKEN_HASH',hash('sha256',$_SERVER['HTTP_X_SETUP_TOKEN']??''))) {http_response_code(404);exit;}
require __DIR__.'/backend/vendor/autoload.php';
$app=require __DIR__.'/backend/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
$lock=fopen(__DIR__.'/backend/storage/setup.lock','c');
if(!flock($lock,LOCK_EX|LOCK_NB)){http_response_code(409);exit;}
if(is_file(__DIR__.'/backend/storage/installed')){http_response_code(410);exit;}
$input=json_decode(file_get_contents('php://input'),true);
if(strlen($input['password']??'')<16){http_response_code(422);exit;}
try {
 if($kernel->call('migrate',['--force'=>true])!==0)throw new RuntimeException('Migration failed');
 if($kernel->call('db:seed',['--force'=>true])!==0)throw new RuntimeException('Seeding failed');
 Illuminate\Support\Facades\DB::transaction(function()use($input){
  if(App\Models\User::where('role','admin')->exists())throw new RuntimeException('Owner already exists');
  App\Models\User::create(['name'=>'Buziness Test','email'=>'buzinesstest1@exelapp.com','password'=>$input['password'],'role'=>'admin','permissions'=>[],'branch_ids'=>[]]);
  Illuminate\Support\Facades\DB::table('business')->update(['name'=>'Buziness Test']);
 });
 file_put_contents(__DIR__.'/backend/storage/installed',date('c'));
 unlink(__FILE__);
 header('Content-Type: application/json');echo json_encode(['installed'=>true]);
}catch(Throwable $e){error_log((string)$e);http_response_code(500);echo 'Initialization failed; inspect server logs.';}

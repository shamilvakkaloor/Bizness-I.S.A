<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class Audit {
 public static function record(?int $user,string $action,string $table,int $id,mixed $before,mixed $after,string $reason='',?int $branch=null): void {
  DB::table('audit_events')->insert(['user_id'=>$user,'action'=>$action,'entity_type'=>$table,'entity_id'=>$id,'branch_id'=>$branch,'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE),'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE),'reason'=>$reason,'ip'=>app()->runningInConsole()?'console':request()->ip(),'created_at'=>now()]);
 }
}

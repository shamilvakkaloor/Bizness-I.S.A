<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  if(!DB::table('business')->exists()) DB::table('business')->insert(['name'=>'My Business','modules'=>json_encode(['manufacturing','restaurant','services','projects','payroll','assets']),'created_at'=>now(),'updated_at'=>now()]);
  if(!DB::table('branches')->exists()) {$b=DB::table('branches')->insertGetId(['name'=>'Main branch','created_at'=>now(),'updated_at'=>now()]);DB::table('warehouses')->insert(['name'=>'Main store','branch_id'=>$b,'created_at'=>now(),'updated_at'=>now()]);}
  $accounts=[['1000','Cash','asset'],['1010','Bank','asset'],['1100','Customers receivable','asset'],['1200','Inventory','asset'],['1300','Input GST','asset'],['1400','Fixed assets','asset'],['1410','Accumulated depreciation','asset'],['1500','Employee advances','asset'],['1990','Interbranch clearing','asset'],['2000','Suppliers payable','liability'],['2100','Output GST','liability'],['2200','Payroll payable','liability'],['2210','Payroll deductions payable','liability'],['2300','Goods received not invoiced','liability'],['3000','Opening equity','equity'],['4000','Sales','income'],['4100','Other income','income'],['5000','Cost of goods sold','expense'],['5100','Operating expenses','expense'],['5200','Payroll expense','expense'],['5300','Depreciation expense','expense'],['5400','Stock adjustments','expense']];
  foreach($accounts as [$code,$name,$type]) DB::table('accounts')->updateOrInsert(['code'=>$code],['name'=>$name,'type'=>$type,'system'=>true,'created_at'=>now(),'updated_at'=>now()]);
 }
}

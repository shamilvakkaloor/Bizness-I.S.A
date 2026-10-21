<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Validator};
use App\Support\{Access,Amount,Audit};
class MasterController {
 const RULES=[
 'products'=>['sku'=>'required|string|max:80','name'=>'required|string|max:200','barcode'=>'nullable|string|max:100','category'=>'nullable|string|max:100','variant'=>'nullable|string|max:100','unit'=>'required|string|max:20','tracking'=>'required|in:none,batch,serial','track_stock'=>'required|boolean','hsn'=>'nullable|string|max:20','tax_bps'=>'required|integer|min:0|max:4000','retail'=>'required|numeric|min:0','wholesale'=>'required|numeric|min:0','reorder'=>'required|numeric|min:0','conversions'=>'nullable|array','conversions.*'=>'integer|min:1|max:100000','active'=>'required|boolean'],
 'parties'=>['name'=>'required|string|max:200','type'=>'required|in:customer,supplier,both','phone'=>'nullable|string|max:30','email'=>'nullable|email|max:255','address'=>'nullable|string|max:1000','gstin'=>'nullable|regex:/^[0-9]{2}[A-Z0-9]{13}$/','state_code'=>'required|digits:2','active'=>'required|boolean'],
 'branches'=>['name'=>'required|string|max:100','address'=>'nullable|string|max:1000'],
 'warehouses'=>['branch_id'=>'required|integer|exists:branches,id','name'=>'required|string|max:100'],
 'accounts'=>['code'=>'required|string|max:20','name'=>'required|string|max:200','type'=>'required|in:asset,liability,equity,income,expense'],
 'employees'=>['branch_id'=>'required|integer|exists:branches,id','name'=>'required|string|max:150','code'=>'required|string|max:50','monthly'=>'required|numeric|min:0','overtime'=>'required|numeric|min:0','active'=>'required|boolean'],
 'prices'=>['party_id'=>'required|integer|exists:parties,id','product_id'=>'required|integer|exists:products,id','price'=>'required|numeric|min:0']];
 public function save(Request $r,string $table,?int $id=null){$id=$this->write($r->all(),$r->user(),$table,$id);return ['id'=>$id];}
 public function write(array $input,$u,string $table,?int $id=null): int {
  abort_unless(isset(self::RULES[$table]),404);Access::require($u,in_array($table,['branches','warehouses','accounts'])?'settings':($table==='employees'?'payroll':'masters'));
  $v=Validator::make($input,self::RULES[$table])->validate();
  if(isset($v['branch_id']))Access::branch($u,(int)$v['branch_id']);
  if($table==='products'){
   $v['retail_paise']=Amount::parse($v['retail']);$v['wholesale_paise']=Amount::parse($v['wholesale']);$v['reorder_milli']=Amount::parse($v['reorder'],3);$v['conversions']=json_encode($v['conversions']??[]);unset($v['retail'],$v['wholesale'],$v['reorder']);
  }
  if($table==='employees'){$v['monthly_paise']=Amount::parse($v['monthly']);$v['overtime_hour_paise']=Amount::parse($v['overtime']);unset($v['monthly'],$v['overtime']);}
  if($table==='prices'){$v['price_paise']=Amount::parse($v['price']);unset($v['price']);}
  return DB::transaction(function()use($table,$id,$v,$u,$input){
   $before=$id?DB::table($table)->lockForUpdate()->find($id):null;if($id)abort_unless($before,404);
   if(isset($before->branch_id))Access::branch($u,$before->branch_id);
   if($table==='warehouses'&&$before&&$before->branch_id!=(int)$v['branch_id']&&DB::table('stock_moves')->where('warehouse_id',$id)->exists())abort(422,'A used warehouse cannot move between branches.');if($table==='accounts'&&$before&&$before->system)abort(422,'System accounts cannot be changed.');
   if($table==='products'&&$before&&DB::table('stock_moves')->where('product_id',$id)->exists())foreach(['unit','tracking','track_stock','conversions'] as $key)if((string)$v[$key] !== (string)$before->$key)abort(422,'Stock tracking and units cannot change after movements exist.');
   foreach(['sku','barcode','code'] as $key)if(!empty($v[$key])){if(DB::table($table)->where($key,$v[$key])->when($id,fn($q)=>$q->where('id','!=',$id))->exists())abort(422,$key.' is already in use.');}
   $v['updated_at']=now();if($id)DB::table($table)->where('id',$id)->update($v);else{$v['created_at']=now();$id=DB::table($table)->insertGetId($v);}
   Audit::record($u->id,$before?'update':'create',$table,$id,$before,$v,$input['reason']??'', $v['branch_id']??null);return $id;
  });
 }
 public function settings(Request $r){Access::require($r->user(),'settings');$v=$r->validate(['name'=>'required|string|max:150','address'=>'nullable|string|max:1000','phone'=>'nullable|string|max:30','gst_mode'=>'required|in:unregistered,regular,composition','gstin'=>'nullable|required_unless:gst_mode,unregistered|regex:/^[0-9]{2}[A-Z0-9]{13}$/','state_code'=>'required|digits:2','locked_until'=>'nullable|date_format:Y-m-d','receipt_width'=>'required|integer|min:48|max:110','modules'=>'required|array','modules.*'=>'in:manufacturing,restaurant,services,projects,payroll,assets']);
  if(!empty($v['gstin'])&&substr($v['gstin'],0,2)!==$v['state_code'])abort(422,'GSTIN state prefix differs from state code.');
  DB::transaction(function()use($v,$r){$before=DB::table('business')->lockForUpdate()->first();$v['modules']=json_encode($v['modules']);$v['updated_at']=now();DB::table('business')->where('id',$before->id)->update($v);Audit::record($r->user()->id,'settings','business',$before->id,$before,$v);});return ['ok'=>true];
 }
 public function users(Request $r){abort_unless($r->user()->role==='admin',403);return \App\Models\User::orderBy('name')->get();}
 public function user(Request $r,?int $id=null){Access::require($r->user(),'users');$v=$r->validate(['name'=>'required|string|max:150','email'=>['required','email','max:255',\Illuminate\Validation\Rule::unique('users')->ignore($id)],'password'=>($id?'nullable':'required').'|string|min:12|max:255','permissions'=>'required|array','permissions.*'=>'in:view,create,approve,cancel,export,costs,accounting,masters,price_override,payroll,audit,settings,users','branch_ids'=>'required|array|min:1','branch_ids.*'=>'integer|exists:branches,id','discount_limit_bps'=>'required|integer|min:0|max:10000','active'=>'required|boolean']);
  abort_unless($r->user()->role==='admin',403);return DB::transaction(function()use($v,$id,$r){$u=$id?\App\Models\User::findOrFail($id):new \App\Models\User;abort_if($u->role==='admin',422,'Use your profile to change admin credentials.');$before=$u->toArray();if(empty($v['password']))unset($v['password']);$u->fill($v+['role'=>'staff']);$u->save();Audit::record($r->user()->id,'staff_save','users',$u->id,$before,$u->toArray());return ['id'=>$u->id];});
 }
}

<?php
namespace App\Services;
use App\Support\Amount;
use Illuminate\Support\Facades\DB;
class StockService {
 public function move(object $d,object $l,int $qty,?int $incoming,string|null $offset,array &$warnings): int {
  $p=DB::table('products')->find($l->product_id);$key=['product_id'=>$l->product_id,'warehouse_id'=>$d->warehouse_id,'lot'=>$l->lot];
  if(!DB::table('stock_balances')->where($key)->exists())DB::table('stock_balances')->insert($key+['expiry'=>$l->expiry]);
  $b=DB::table('stock_balances')->where($key)->lockForUpdate()->first();
  if($b->expiry&&$l->expiry&&$b->expiry!==$l->expiry)DocumentService::fail('Batch expiry differs from the existing batch.');
  if($qty<0&&$b->expiry&&$b->expiry<$d->date)DocumentService::fail('This batch has expired.');
  if($p->tracking==='serial'&&(($qty>0&&DB::table('stock_balances')->where('product_id',$p->id)->where('lot',$l->lot)->sum('quantity_milli')>0)||($qty<0&&$b->quantity_milli<1000)))DocumentService::fail('Serial number is duplicated or unavailable.');
  if(in_array($d->kind,['production','transfer'])&&$qty<0&&$b->quantity_milli<abs($qty))DocumentService::fail('Receive component/transfer stock first so its actual cost is known.');
  $unit=$b->quantity_milli>0?Amount::divide($b->value_paise*1000,$b->quantity_milli):$b->last_cost_paise;
  $cost=$qty>0?($incoming??Amount::divide($qty*$unit,1000)):-Amount::divide(abs($qty)*$unit,1000);
  $newQty=$b->quantity_milli+$qty;$newValue=$b->value_paise+$cost;$uncovered=$qty<0?max(0,abs($qty)-max(0,$b->quantity_milli)):0;
  if($qty>0&&$b->quantity_milli<0){
   $remaining=min($qty,abs($b->quantity_milli));$realUnit=Amount::divide($cost*1000,$qty);
   $issues=DB::table('stock_moves')->where($key)->where('uncovered_milli','>',0)->orderBy('id')->get();
   foreach($issues as $issue){if(!$remaining)break;$cover=min($remaining,$issue->uncovered_milli);$estimated=Amount::divide(abs($issue->value_paise)*1000,abs($issue->quantity_milli));$delta=Amount::divide($cover*($realUnit-$estimated),1000);
    if($delta){$svc=app(DocumentService::class);$svc->entry($d,$issue->offset_account??'5000',$delta,null,'Negative stock cost settlement');$svc->entry($d,'1200',-$delta,null,'Negative stock cost settlement');$newValue-=$delta;DB::table('stock_moves')->insert($key+['document_id'=>$d->id,'line_id'=>null,'quantity_milli'=>0,'value_paise'=>-$delta,'provisional'=>false,'uncovered_milli'=>0,'offset_account'=>null,'created_at'=>now()]);}
    DB::table('stock_moves')->where('id',$issue->id)->update(['uncovered_milli'=>$issue->uncovered_milli-$cover]);$remaining-=$cover;
   }
   // Historical issues retain provisional=true as an audit marker; settlement is a new entry.
  }
  if($newQty<0)$warnings[]='Negative stock: '.$p->name.' ('.($newQty/1000).' '.$p->unit.'). Profit may be provisional.';
  if($newQty===0&&$newValue!==0){$svc=app(DocumentService::class);$svc->entry($d,'5000',$newValue,null,'Stock rounding settlement');$svc->entry($d,'1200',-$newValue,null,'Stock rounding settlement');DB::table('stock_moves')->insert($key+['document_id'=>$d->id,'line_id'=>null,'quantity_milli'=>0,'value_paise'=>-$newValue,'provisional'=>false,'uncovered_milli'=>0,'offset_account'=>null,'created_at'=>now()]);$newValue=0;}
  DB::table('stock_balances')->where('id',$b->id)->update(['quantity_milli'=>$newQty,'value_paise'=>$newValue,'last_cost_paise'=>$qty>0?Amount::divide($cost*1000,$qty):$unit,'expiry'=>$b->expiry??$l->expiry]);
  DB::table('stock_moves')->insert($key+['document_id'=>$d->id,'line_id'=>$l->id,'quantity_milli'=>$qty,'value_paise'=>$cost,'provisional'=>$uncovered>0,'uncovered_milli'=>$uncovered,'offset_account'=>$offset,'created_at'=>now()]);return $cost;
 }
}

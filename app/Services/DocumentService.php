<?php
namespace App\Services;
use App\Models\User;
use App\Support\{Access,Amount,Audit};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;
class DocumentService {
 const ITEMS=['sale','purchase','sale_return','purchase_return','quote','sales_order','purchase_order','goods_receipt','delivery','stock_adjustment','transfer','production','opening_stock'];
 const KINDS=['sale','purchase','sale_return','purchase_return','quote','sales_order','purchase_order','goods_receipt','delivery','stock_adjustment','transfer','production','receipt','payment','expense','income','journal','opening','opening_stock'];
 public static function fail(string $message): never {throw ValidationException::withMessages(['document'=>$message]);}
 public function snapshot(int $id): array {return ['document'=>(array)DB::table('documents')->find($id),'lines'=>DB::table('document_lines')->where('document_id',$id)->get()->toArray()];}
 public function save(array $input,User $user,?int $id=null): int {
  Access::require($user,'create');
  $v=Validator::make($input,['kind'=>'required|in:'.implode(',',self::KINDS),'date'=>'required|date_format:Y-m-d','due_date'=>'nullable|date_format:Y-m-d','branch_id'=>'required|integer|exists:branches,id','warehouse_id'=>'nullable|integer|exists:warehouses,id','target_warehouse_id'=>'nullable|integer|exists:warehouses,id','party_id'=>'nullable|integer|exists:parties,id','related_id'=>'nullable|integer|exists:documents,id','tax_treatment'=>'required|in:taxable,exempt,nil_rated,non_gst','state_code'=>'required|digits:2','price_tier'=>'required|in:retail,wholesale','notes'=>'nullable|string|max:4000','reason'=>'nullable|string|max:1000','lines'=>'required|array|min:1|max:200','data'=>'nullable|array','version'=>'nullable|integer|min:1'])->validate();
  Access::branch($user,(int)$v['branch_id']);
  return DB::transaction(function()use($v,$user,$id){
   $before=null;
   if($id){$old=DB::table('documents')->lockForUpdate()->find($id);abort_unless($old,404);Access::branch($user,$old->branch_id);if($old->status!=='draft')self::fail('Posted records cannot be edited. Reverse or return them.');if((int)($v['version']??0)!==$old->version)self::fail('Another user changed this draft. Reload before saving.');if(in_array($old->kind,['purchase','purchase_return','purchase_order','goods_receipt','production']))Access::require($user,'costs');if(in_array($old->kind,['journal','opening','opening_stock']))Access::require($user,'accounting');$before=$this->snapshot($id);}
   if(in_array($v['kind'],['journal','opening','opening_stock','stock_adjustment','production'])) Access::require($user,'accounting');
   if(in_array($v['kind'],['purchase','goods_receipt','purchase_order','purchase_return'])) Access::require($user,'costs');
   $biz=DB::table('business')->first();
   if($v['tax_treatment']==='taxable' && $biz->gst_mode!=='regular')self::fail('Only a regular GST-registered business may charge GST.');
   if(in_array($v['tax_treatment'],['exempt','nil_rated','non_gst'])&&$biz->gst_mode==='regular'&&empty($v['reason']))self::fail('Record the basis for this tax treatment.');
   if($v['kind']==='production'&&!in_array('manufacturing',json_decode($biz->modules,true)))self::fail('Enable manufacturing in settings.');
   if(!empty($v['warehouse_id'])) { $w=DB::table('warehouses')->find($v['warehouse_id']);if($w->branch_id!=$v['branch_id'])self::fail('Warehouse belongs to another branch.'); }
   if(!empty($v['target_warehouse_id'])) { $w=DB::table('warehouses')->find($v['target_warehouse_id']);Access::branch($user,$w->branch_id); }
   if(in_array($v['kind'],['sale','purchase','sale_return','purchase_return','receipt','payment'])&&empty($v['party_id']))self::fail('Choose a customer or supplier (use Walk-in customer for POS).');
   $lines=[];$net=0;$tax=0;
   foreach($v['lines'] as $l){
    Validator::make($l,['product_id'=>'nullable|integer|exists:products,id','account_id'=>'nullable|integer|exists:accounts,id','source_line_id'=>'nullable|integer|exists:document_lines,id','description'=>'nullable|string|max:255','lot'=>'nullable|string|max:120','expiry'=>'nullable|date_format:Y-m-d','discount'=>'nullable|numeric|min:0|max:100','unit_multiplier'=>'nullable|integer|min:1|max:100000'])->validate();
    $p=empty($l['product_id'])?null:DB::table('products')->find($l['product_id']);
    if($p&&!$p->active)self::fail('Product is inactive.');
    $q=Amount::parse($l['quantity']??'1',3);$mult=(int)($l['unit_multiplier']??1);
    if($p&&$mult!==1&&!in_array($mult,array_values(json_decode($p->conversions??'{}',true)),true))self::fail('Unit multiplier is not configured for this product.');
    if($q===0 || ($q<0&&!in_array($v['kind'],['stock_adjustment','production'])))self::fail('Quantity must be positive.');
    $discount=Amount::parse($l['discount']??'0');if($discount<0||$discount>10000)self::fail('Invalid discount.');
    if($user->role!=='admin'&&in_array($v['kind'],['sale','quote','sales_order'])&&$discount>$user->discount_limit_bps)self::fail('Discount exceeds your permission.');
    $price=Amount::parse($l['price']??'0');if(abs($q*$mult)>100000000||$price>100000000)self::fail('Line exceeds supported quantity or price limits (100,000 base units / INR 1,000,000 per entered unit).');if($price<0)self::fail('Price cannot be negative.');
    if($p && in_array($v['kind'],['sale','quote','sales_order'])){
     $expected=$v['price_tier']==='wholesale'?$p->wholesale_paise:$p->retail_paise;
     if(!empty($v['party_id'])) $expected=DB::table('prices')->where('party_id',$v['party_id'])->where('product_id',$p->id)->value('price_paise')??$expected;
     $expected*=$mult;
     if($price!==$expected&&!$user->canDo('price_override'))self::fail('Price override permission is required.');
    }
    $basis=Amount::divide(abs($q)*$price,1000);$n=$basis-Amount::divide($basis*$discount,10000);
    $rate=$v['tax_treatment']==='taxable'?(int)($p?->tax_bps??0):0;$t=Amount::divide($n*$rate,10000);
    if($rate===0&&$v['tax_treatment']==='taxable')self::fail('Taxable products require a configured GST rate.');
    if($p&&$p->tracking!=='none'&&empty($l['lot']))self::fail('Enter batch or serial for '.$p->name);
    if($p&&$p->tracking==='serial'&&abs($q*$mult)!==1000)self::fail('Each serial-number line must have quantity one.');
    if($p&&$p->tracking==='batch'&&empty($l['expiry']))self::fail('Enter batch expiry date.');
    if(in_array($v['kind'],self::ITEMS)&&!$p)self::fail('Choose a product or service for each line.');
    if(!in_array($v['kind'],self::ITEMS)&&empty($l['account_id']))self::fail('Choose an account for each voucher line.');
    $lines[]=['product_id'=>$p?->id,'account_id'=>$l['account_id']??null,'source_line_id'=>$l['source_line_id']??null,'description'=>$l['description']??$p?->name??'Entry','quantity_milli'=>$q*$mult,'price_paise'=>Amount::divide($price,$mult),'discount_bps'=>$discount,'tax_bps'=>$rate,'net_paise'=>$n,'tax_paise'=>$t,'debit_paise'=>Amount::parse($l['debit']??'0'),'credit_paise'=>Amount::parse($l['credit']??'0'),'lot'=>$l['lot']??'','expiry'=>$l['expiry']??null,'snapshot'=>json_encode($p)];$net+=$n;$tax+=$t;
   }
   if($net+$tax>1000000000000)self::fail('Document exceeds the supported amount limit.');$data=['kind'=>$v['kind'],'date'=>$v['date'],'due_date'=>$v['due_date']??null,'branch_id'=>$v['branch_id'],'warehouse_id'=>$v['warehouse_id']??null,'target_warehouse_id'=>$v['target_warehouse_id']??null,'party_id'=>$v['party_id']??null,'related_id'=>$v['related_id']??null,'tax_treatment'=>$v['tax_treatment'],'state_code'=>$v['state_code'],'price_tier'=>$v['price_tier'],'notes'=>$v['notes']??'','reason'=>$v['reason']??'','net_paise'=>$net,'tax_paise'=>$tax,'total_paise'=>$net+$tax,'data'=>json_encode($v['data']??[]),'updated_at'=>now()];
   if($id){$data['version']=$old->version+1;DB::table('documents')->where('id',$id)->update($data);DB::table('document_lines')->where('document_id',$id)->delete();}
   else {$data['created_by']=$user->id;$data['created_at']=now();$id=DB::table('documents')->insertGetId($data);}
   foreach($lines as $l)DB::table('document_lines')->insert(['document_id'=>$id]+$l);
   Audit::record($user->id,$before?'update':'create','documents',$id,$before,$this->snapshot($id),$v['reason']??'',(int)$v['branch_id']);return $id;
  },3);
 }
 public function entry(object $d,string|int $account,int $signed,?int $party=null,string $memo=''): void {
  if(!$signed)return;$id=is_int($account)?$account:DB::table('accounts')->where('code',$account)->value('id');if(!$id)self::fail('Account missing: '.$account);
  DB::table('ledger_entries')->insert(['document_id'=>$d->id,'account_id'=>$id,'branch_id'=>$d->branch_id,'party_id'=>$party,'date'=>$d->date,'debit_paise'=>max(0,$signed),'credit_paise'=>max(0,-$signed),'memo'=>$memo]);
 }
 private function matchSource(object $d,object $line,string $sourceKind): ?object {
  if(!$d->related_id)return null;
  $src=DB::table('documents')->lockForUpdate()->find($d->related_id);
  if(!$src||$src->status!=='posted'||$src->kind!==$sourceKind||$src->branch_id!==$d->branch_id||$src->party_id!==$d->party_id)self::fail('Source document is not a matching posted '.$sourceKind.'.');
  $sl=DB::table('document_lines')->where('document_id',$src->id)->where('id',$line->source_line_id)->first();
  if(!$sl||$sl->product_id!==$line->product_id||$sl->lot!==$line->lot)self::fail('Select the matching source line.');
  $used=DB::table('document_lines as l')->join('documents as d','d.id','=','l.document_id')->where('l.source_line_id',$sl->id)->where('d.kind',$d->kind)->where('d.status','posted')->sum('l.quantity_milli');
  // Count all current lines too, to prevent repeating a source line within this draft.
  $current=DB::table('document_lines')->where('document_id',$d->id)->where('source_line_id',$sl->id)->sum('quantity_milli');
  if($used+$current>$sl->quantity_milli)self::fail('Quantity exceeds unprocessed source quantity.');
  return $sl;
 }
 public function post(int $id,User $user): void {
  Access::require($user,'approve');
  DB::transaction(function()use($id,$user){
   // One business lock serialises posting; stock and ledgers commit atomically.
   $biz=DB::table('business')->lockForUpdate()->first();$d=DB::table('documents')->lockForUpdate()->find($id);abort_unless($d,404);Access::branch($user,$d->branch_id);
   if($d->status==='posted')return;if($d->status!=='draft')self::fail('Only drafts may be posted.');
   if($biz->locked_until&&$d->date<=$biz->locked_until)self::fail('This accounting period is locked.');
   if($d->tax_treatment==='taxable'&&($biz->gst_mode!=='regular'||!$biz->gstin))self::fail('Complete GST registration settings first.');
   $before=$this->snapshot($id);$lines=DB::table('document_lines')->where('document_id',$id)->orderBy('quantity_milli')->get();$warnings=[];
   $stock=app(StockService::class);$k=$d->kind;$cash=json_decode($d->data??'{}',true)['cash_account']??'1000';
   if(!in_array($cash,['1000','1010'],true))self::fail('Choose Cash or Bank.');
   if(in_array($k,['journal','opening'])){
    Access::require($user,'accounting');foreach($lines as $l){if($l->debit_paise<0||$l->credit_paise<0||($l->debit_paise&&$l->credit_paise))self::fail('Use either debit or credit on a journal line.');$this->entry($d,(int)$l->account_id,$l->debit_paise-$l->credit_paise,$d->party_id);}
   }elseif(in_array($k,['receipt','payment'])){
    $sign=$k==='receipt'?1:-1;$this->entry($d,$cash,$sign*$d->total_paise);$this->entry($d,$k==='receipt'?'1100':'2000',-$sign*$d->total_paise,$d->party_id);
    $alloc=json_decode($d->data??'{}',true)['allocations']??[];$sum=0;
    foreach($alloc as $a){$invoice=DB::table('documents')->lockForUpdate()->find($a['invoice_id']??0);$value=Amount::parse($a['amount']??0);
     if(!$invoice||$invoice->status!=='posted'||$invoice->kind!==($k==='receipt'?'sale':'purchase')||$invoice->party_id!==$d->party_id||$invoice->branch_id!==$d->branch_id||$value<=0)self::fail('Invalid payment allocation.');
     $used=DB::table('allocations')->where('invoice_id',$invoice->id)->sum('amount_paise');$returns=DB::table('documents')->where('related_id',$invoice->id)->where('status','posted')->where('kind',$k==='receipt'?'sale_return':'purchase_return')->sum('total_paise');if($used+$value>$invoice->total_paise-$returns)self::fail('Allocation exceeds invoice balance.');
     DB::table('allocations')->insert(['payment_id'=>$id,'invoice_id'=>$invoice->id,'amount_paise'=>$value]);$sum+=$value;
    }if($sum>$d->total_paise)self::fail('Allocations exceed payment.');
   }elseif(in_array($k,['expense','income'])){
    $s=$k==='expense'?1:-1;foreach($lines as $l){$a=DB::table('accounts')->find($l->account_id);if($a->type!==($k==='expense'?'expense':'income'))self::fail('Choose the correct expense/income account.');$this->entry($d,(int)$l->account_id,$s*$l->net_paise);}
    if($d->tax_paise)self::fail('Use a purchase/sales invoice for taxable expenses or income.');$this->entry($d,$cash,-$s*$d->total_paise);
   }else{
    $productionCost=0;$outputs=$lines->filter(fn($l)=>$l->quantity_milli>0);
    if($k==='production'&&($outputs->count()!==1||$lines->where('quantity_milli','<',0)->isEmpty()))self::fail('Production needs component lines with negative quantities and exactly one positive output line.');
    foreach($lines as $l){
     $p=DB::table('products')->find($l->product_id);if(!$p)self::fail('Product missing.');if(in_array($k,['production','transfer','opening_stock','stock_adjustment'])&&!$p->track_stock)self::fail('This operation requires stock-tracked products.');$move=0;$value=null;$offset=null;
     if(in_array($k,['quote','sales_order','purchase_order']))continue;
     if($p->track_stock&&!$d->warehouse_id)self::fail('Choose a warehouse for stock items.');
     if($k==='goods_receipt'){$this->matchSource($d,$l,'purchase_order');$move=$l->quantity_milli;$value=$l->net_paise;$offset='2300';}
     if($k==='delivery'){$this->matchSource($d,$l,'sales_order');$move=-$l->quantity_milli;$offset='5000';}
     if($k==='purchase'){$sl=$this->matchSource($d,$l,'goods_receipt');if($sl){$received=Amount::divide($sl->net_paise*$l->quantity_milli,$sl->quantity_milli);$this->entry($d,'2300',$received);$this->entry($d,'5100',$l->net_paise-$received,null,'Purchase price variance');}else{$move=$l->quantity_milli;$value=$l->net_paise;$offset='2000';}}
     if($k==='sale'){if(!$this->matchSource($d,$l,'delivery')){$move=-$l->quantity_milli;$offset='5000';}}
     if(in_array($k,['sale_return','purchase_return'])){
      $src=$this->matchSource($d,$l,$k==='sale_return'?'sale':'purchase');if(!$src)self::fail('Returns require the original invoice and source line.');
      if($src->tax_bps!==$l->tax_bps||$src->price_paise!==$l->price_paise||$src->discount_bps!==$l->discount_bps)self::fail('Return price, discount and GST must match the source.');
      $move=$k==='sale_return'?$l->quantity_milli:-$l->quantity_milli;$offset=$k==='sale_return'?'5000':'2000';
      if($k==='sale_return'){$original=DB::table('stock_moves')->where('line_id',$src->id)->where('quantity_milli','<',0)->first();
       if(!$original&&DB::table('documents')->where('id',$d->related_id)->value('related_id')){$deliveryLine=$src->source_line_id;$original=DB::table('stock_moves')->where('line_id',$deliveryLine)->where('quantity_milli','<',0)->first();}
       if($original&&$original->provisional)self::fail('Resolve provisional cost before returning this sale.');$value=$original?Amount::divide(abs($original->value_paise)*$l->quantity_milli,abs($original->quantity_milli)):$l->net_paise;}
     }
     if(in_array($k,['stock_adjustment','opening_stock'])){$move=$l->quantity_milli;$value=$move>0?$l->net_paise:null;$offset=$k==='opening_stock'?'3000':'5400';if(!$d->reason)self::fail('Stock adjustment requires a reason.');}
     if($k==='transfer'){if(!$d->target_warehouse_id||$d->target_warehouse_id===$d->warehouse_id)self::fail('Choose a different destination warehouse.');$move=-$l->quantity_milli;}
     if($k==='production'){$move=$l->quantity_milli;$value=$move>0?$productionCost:null;}
     if($p->track_stock&&$move){
      $cost=$stock->move($d,$l,$move,$value,$offset,$warnings);
      if($k==='production'&&$move<0)$productionCost+=abs($cost);
      if($k==='transfer'){$target=clone $d;$target->warehouse_id=$d->target_warehouse_id;$target->branch_id=DB::table('warehouses')->where('id',$target->warehouse_id)->value('branch_id');$stock->move($target,$l,-$move,abs($cost),null,$warnings);$this->entry($d,'1200',$cost);$this->entry($d,'1990',-$cost);$this->entry($target,'1200',-$cost);$this->entry($target,'1990',$cost);}
      if(in_array($k,['goods_receipt','purchase'])&&$move>0)$this->entry($d,'1200',$cost);
      if($k==='goods_receipt')$this->entry($d,'2300',-$cost);
      if(in_array($k,['sale','delivery'])&&$move<0){$this->entry($d,'5000',-$cost);$this->entry($d,'1200',$cost);}
      if($k==='sale_return'){$this->entry($d,'1200',$cost);$this->entry($d,'5000',-$cost);}
      if($k==='purchase_return'){$this->entry($d,'1200',$cost);$this->entry($d,'5100',-$cost-$l->net_paise,null,'Return valuation variance');}
      if(in_array($k,['stock_adjustment','opening_stock'])){$this->entry($d,'1200',$cost);$this->entry($d,$k==='opening_stock'?'3000':'5400',-$cost);}
     }elseif(in_array($k,['purchase','goods_receipt'])&&!$d->related_id){$this->entry($d,'5100',$l->net_paise);if($k==='goods_receipt')$this->entry($d,'2300',-$l->net_paise);}
     if($k==='purchase_return'&&!$p->track_stock)$this->entry($d,'5100',-$l->net_paise);
    }
    if(in_array($k,['sale','sale_return'])){$s=$k==='sale'?1:-1;$this->entry($d,'1100',$s*$d->total_paise,$d->party_id);$this->entry($d,'4000',-$s*$d->net_paise);$this->entry($d,'2100',-$s*$d->tax_paise);}
    if(in_array($k,['purchase','purchase_return'])){$s=$k==='purchase'?1:-1;$this->entry($d,'2000',-$s*$d->total_paise,$d->party_id);$this->entry($d,'1300',$s*$d->tax_paise);}
   }
   $this->assertBalanced($id);
   DB::table('documents')->where('id',$id)->update(['status'=>'posted','number'=>strtoupper(substr($k,0,3)).'-'.str_replace('-','',$d->date).'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),'posted_by'=>$user->id,'posted_at'=>now(),'warnings'=>json_encode(array_values(array_unique($warnings))),'business_snapshot'=>json_encode($biz),'party_snapshot'=>json_encode($d->party_id?DB::table('parties')->find($d->party_id):null),'version'=>$d->version+1,'updated_at'=>now()]);
   Audit::record($user->id,'post','documents',$id,$before,$this->snapshot($id),'',$d->branch_id);
  },3);
 }
 public function assertBalanced(int $id): void { $q=DB::table('ledger_entries')->where('document_id',$id);if((clone $q)->sum('debit_paise')!==(clone $q)->sum('credit_paise'))self::fail('Posting rejected: accounting entries are not balanced.'); }
 public function reverse(int $id,User $u,string $reason,string $date): int {
  Access::require($u,'cancel');if(strlen(trim($reason))<5)self::fail('Explain why this document is being reversed.');
  return DB::transaction(function()use($id,$u,$reason,$date){
   $biz=DB::table('business')->lockForUpdate()->first();$d=DB::table('documents')->lockForUpdate()->find($id);abort_unless($d,404);Access::branch($u,$d->branch_id);
   if(in_array($d->kind,['payroll','depreciation']))self::fail('Use an accounting adjustment for system-generated payroll or depreciation; automatic reversal is not supported in this build.');if($d->status!=='posted'||$d->reversal_of)self::fail('Only an original posted document can be reversed.');
   if($biz->locked_until&&$date<=$biz->locked_until)self::fail('Reversal date is in a locked period.');
   if(DB::table('documents')->where('related_id',$id)->where('status','posted')->exists()||DB::table('allocations')->where('invoice_id',$id)->exists())self::fail('Reverse linked documents/payments first.');
   if(DB::table('ledger_entries')->where('document_id',$id)->whereIn('memo',['Negative stock cost settlement','Stock rounding settlement'])->exists())self::fail('This document settled prior stock costs. Use a return or adjustment.');$moves=DB::table('stock_moves')->where('document_id',$id)->get();
   foreach($moves as $m){Access::branch($u,(int)DB::table('warehouses')->where('id',$m->warehouse_id)->value('branch_id'));if($m->provisional||DB::table('stock_moves')->where('product_id',$m->product_id)->where('warehouse_id',$m->warehouse_id)->where('lot',$m->lot)->where('id','>',$m->id)->where('document_id','!=',$id)->exists())self::fail('Later stock movements exist. Use a linked return or adjustment to preserve costing.');}
   $data=(array)$d;unset($data['id']);$data=array_merge($data,['number'=>'REV-'.$id.'-'.str_replace('-','',$date),'date'=>$date,'kind'=>'reversal','reversal_of'=>$id,'related_id'=>null,'reason'=>$reason,'created_by'=>$u->id,'posted_by'=>$u->id,'created_at'=>now(),'updated_at'=>now(),'posted_at'=>now(),'version'=>1]);$rid=DB::table('documents')->insertGetId($data);$r=DB::table('documents')->find($rid);
   foreach(DB::table('ledger_entries')->where('document_id',$id)->get() as $e)$this->entry($r,(int)$e->account_id,$e->credit_paise-$e->debit_paise,$e->party_id,'Reversal of '.$d->number);
   foreach($moves as $m){DB::table('stock_balances')->where('product_id',$m->product_id)->where('warehouse_id',$m->warehouse_id)->where('lot',$m->lot)->decrement('quantity_milli',$m->quantity_milli);DB::table('stock_balances')->where('product_id',$m->product_id)->where('warehouse_id',$m->warehouse_id)->where('lot',$m->lot)->decrement('value_paise',$m->value_paise);$copy=(array)$m;unset($copy['id']);$copy['document_id']=$rid;$copy['quantity_milli']=-$m->quantity_milli;$copy['value_paise']=-$m->value_paise;$copy['created_at']=now();DB::table('stock_moves')->insert($copy);}
   DB::table('allocations')->where('payment_id',$id)->delete();DB::table('documents')->where('id',$id)->update(['status'=>'reversed','updated_at'=>now()]);Audit::record($u->id,'reverse','documents',$id,$d,['reversal_id'=>$rid],$reason,$d->branch_id);return $rid;
  },3);
 }
}

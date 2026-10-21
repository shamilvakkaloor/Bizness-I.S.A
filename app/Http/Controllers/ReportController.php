<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Access;
class ReportController {
 public function financial(Request $r){Access::require($r->user(),'accounting');$v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d']);$to=$v['to']??now()->toDateString();$from=$v['from']??substr($to,0,4).'-04-01';if($from>$to)$from=((int)substr($to,0,4)-1).'-04-01';
  $base=Access::scope(DB::table('ledger_entries as l')->join('accounts as a','a.id','=','l.account_id'),$r->user(),'l.branch_id')->where('l.date','<=',$to);
  $aggregate=fn($q)=>$q->select('a.code','a.name','a.type')->selectRaw('SUM(l.debit_paise-l.credit_paise) as balance_paise')->groupBy('a.code','a.name','a.type')->orderBy('a.code')->get();
  $balance=$aggregate(clone $base);$period=$aggregate((clone $base)->where('l.date','>=',$from)->whereIn('a.type',['income','expense']));$retained=0;foreach($balance as $a)if(in_array($a->type,['income','expense']))$retained-=(int)$a->balance_paise;
  return ['from'=>$from,'to'=>$to,'balance_sheet'=>$balance->whereIn('type',['asset','liability','equity'])->values(),'accumulated_earnings_paise'=>$retained,'profit_loss'=>$period];
 }
 public function outstanding(Request $r){Access::require($r->user(),'accounting');$kind=$r->input('type','sale');abort_unless(in_array($kind,['sale','purchase']),422);$invoices=Access::scope(DB::table('documents'),$r->user())->where('kind',$kind)->where('status','posted')->orderBy('date')->get();$result=[];
  foreach($invoices as $d){$paid=DB::table('allocations')->where('invoice_id',$d->id)->sum('amount_paise');$returns=DB::table('documents')->where('related_id',$d->id)->where('kind',$kind==='sale'?'sale_return':'purchase_return')->where('status','posted')->sum('total_paise');$balance=$d->total_paise-$paid-$returns;if($balance){$due=$d->due_date??$d->date;$result[]=['id'=>$d->id,'number'=>$d->number,'party'=>DB::table('parties')->where('id',$d->party_id)->value('name'),'due_date'=>$due,'days_overdue'=>max(0,(int)\Carbon\Carbon::parse($due)->diffInDays(now(),false)),'balance_paise'=>$balance];}}
  return ['invoices'=>$result,'note'=>'Unallocated advances and opening journals appear in the party ledger, not invoice ageing.'];
 }
 public function party(Request $r,int $id){Access::require($r->user(),'accounting');$q=Access::scope(DB::table('ledger_entries'),$r->user())->where('party_id',$id)->orderBy('date')->orderBy('id');$balance=0;$rows=[];foreach($q->get()as $e){$balance+=$e->debit_paise-$e->credit_paise;$e->balance_paise=$balance;$rows[]=$e;}return ['entries'=>$rows,'balance_paise'=>$balance];}
}

<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,Validator};
use Illuminate\Support\Str;
use App\Support\{Access,Audit};
use App\Services\DocumentService;
use PhpOffice\PhpSpreadsheet\IOFactory;
class ImportController {
 public function preview(Request $r){Access::require($r->user(),'masters');$v=$r->validate(['file'=>'required|file|max:4096|mimes:csv,txt,xlsx','table'=>'required|in:products,parties,employees,documents']);$file=$r->file('file');$rows=[];
  if(strtolower($file->getClientOriginalExtension())==='xlsx'){
   $zip=new \ZipArchive;abort_unless($zip->open($file->getRealPath())===true,422,'Invalid XLSX');$expanded=0;for($i=0;$i<$zip->numFiles;$i++){$expanded+=$zip->statIndex($i)['size'];if($expanded>25*1024*1024){$zip->close();abort(422,'Workbook expands beyond the import limit.');}}$zip->close();
   $reader=IOFactory::createReader('Xlsx');$reader->setReadDataOnly(true);foreach($reader->listWorksheetInfo($file->getRealPath()) as $info)abort_if($info['totalRows']>501||$info['totalColumns']>40,422,'Workbook exceeds 500 data rows or 40 columns.');$reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {public function readCell(string $columnAddress,int $row,string $worksheetName=''): bool {return $row<=501&&\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress)<=40;}});$sheet=$reader->load($file->getRealPath())->getActiveSheet();abort_if($sheet->getHighestDataRow()>501,422,'Maximum 500 rows per import.');$rows=$sheet->toArray(null,false,false,false);
  }else{$h=fopen($file->getRealPath(),'r');while(($line=fgetcsv($h,0,',','"',''))!==false){$rows[]=$line;if(count($rows)>501){fclose($h);abort(422,'Maximum 500 rows per import.');}}fclose($h);}
  abort_unless(count($rows)>1,422,'The file must contain a header and data.');$headers=array_map(fn($s)=>trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$s)),array_shift($rows));abort_if(count($headers)!==count(array_unique($headers)),422,'Duplicate column names.');$out=[];$errors=[];
  foreach($rows as $i=>$values){if(!array_filter($values,fn($x)=>$x!==null&&$x!==''))continue;$row=[];foreach($headers as $j=>$header)$row[$header]=trim((string)($values[$j]??''));foreach(['track_stock','active']as $key)if(isset($row[$key]))$row[$key]=in_array(strtolower($row[$key]),['1','true','yes'],true);foreach($row as $key=>$value)if($value==='')$row[$key]=null;
   if($v['table']!=='documents'){$validation=Validator::make($row,MasterController::RULES[$v['table']]);if($validation->fails())$errors[]=['row'=>$i+2,'errors'=>$validation->errors()->all()];}else if(empty($row['reference'])||empty($row['kind'])||empty($row['date']))$errors[]=['row'=>$i+2,'errors'=>['reference, kind and date are required.']];$out[]=$row;
  }
  $token=(string)Str::uuid();Storage::put('imports/'.$token.'.json',json_encode(['user'=>$r->user()->id,'created'=>time(),'table'=>$v['table'],'rows'=>$out,'errors'=>$errors]));$r->session()->put('import_token',$token);return ['token'=>$token,'headers'=>$headers,'rows'=>array_slice($out,0,20),'total'=>count($out),'errors'=>$errors];
 }
 public function apply(Request $r,MasterController $master,DocumentService $svc){Access::require($r->user(),'masters');$v=$r->validate(['token'=>'required|uuid']);abort_unless($r->session()->get('import_token')===$v['token'],403);$path='imports/'.$v['token'].'.json';abort_unless(Storage::exists($path),410);$data=json_decode(Storage::get($path),true);abort_unless($data['user']===$r->user()->id&&time()-$data['created']<1800,410,'Import preview expired.');abort_if(count($data['errors'])>0,422,'Correct the reported rows and upload again.');
  $result=DB::transaction(function()use($data,$r,$master,$svc){$ids=[];if($data['table']==='documents'){
   foreach(collect($data['rows'])->groupBy('reference')as $ref=>$rows){$head=$rows->first();$doc=['kind'=>$head['kind'],'date'=>$head['date'],'branch_id'=>$head['branch_id'],'warehouse_id'=>$head['warehouse_id']??null,'party_id'=>$head['party_id']??null,'tax_treatment'=>$head['tax_treatment']??'non_gst','state_code'=>$head['state_code']??'32','price_tier'=>'retail','reason'=>$head['reason']??'Spreadsheet import','notes'=>'Imported reference: '.$ref,'lines'=>[]];foreach($rows as $row)$doc['lines'][]=array_filter(['product_id'=>$row['product_id']??null,'account_id'=>$row['account_id']??null,'quantity'=>$row['quantity']??'1','price'=>$row['price']??'0','debit'=>$row['debit']??'0','credit'=>$row['credit']??'0','lot'=>$row['lot']??null,'expiry'=>$row['expiry']??null,'description'=>$row['description']??null],fn($x)=>$x!==null);$ids[]=$svc->save($doc,$r->user());}
  }else foreach($data['rows']as $row)$ids[]=$master->write($row,$r->user(),$data['table']);Audit::record($r->user()->id,'import',$data['table'],0,null,['ids'=>$ids]);return ['count'=>count($ids),'ids'=>$ids];});Storage::delete($path);$r->session()->forget('import_token');return $result;
 }
}

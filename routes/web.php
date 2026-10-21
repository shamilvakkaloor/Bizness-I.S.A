<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,BusinessController,MasterController,OperationsController,PayrollController,ImportController};
Route::get('/login',fn()=>view('login'))->name('login')->middleware('guest');
Route::post('/login',[AuthController::class,'login'])->middleware('guest');
Route::middleware('auth')->group(function(){
 Route::get('/',fn()=>view('app'));Route::post('/logout',[AuthController::class,'logout']);Route::post('/api/password',[AuthController::class,'password']);
 Route::get('/api/financial',[\App\Http\Controllers\ReportController::class,'financial']);Route::get('/api/outstanding',[\App\Http\Controllers\ReportController::class,'outstanding']);Route::get('/api/party-ledger/{id}',[\App\Http\Controllers\ReportController::class,'party']);Route::post('/api/documents/{id}/checkout',[BusinessController::class,'checkout']);
 Route::get('/api/users',[MasterController::class,'users']);
 Route::get('/api/bootstrap',[BusinessController::class,'bootstrap']);Route::get('/api/dashboard',[BusinessController::class,'dashboard']);
 Route::get('/api/documents',[BusinessController::class,'documents']);Route::get('/api/documents/{id}',[BusinessController::class,'document']);Route::post('/api/documents',[BusinessController::class,'save']);Route::put('/api/documents/{id}',[BusinessController::class,'save']);Route::post('/api/documents/{id}/post',[BusinessController::class,'post']);Route::post('/api/documents/{id}/reverse',[BusinessController::class,'reverse']);
 Route::get('/api/stock',[BusinessController::class,'stock']);Route::get('/api/reports',[BusinessController::class,'report']);Route::get('/api/audit',[BusinessController::class,'audit']);Route::get('/api/export',[BusinessController::class,'export']);Route::get('/print/{id}',[BusinessController::class,'print']);
 Route::post('/api/masters/{table}',[MasterController::class,'save']);Route::put('/api/masters/{table}/{id}',[MasterController::class,'save']);Route::put('/api/settings',[MasterController::class,'settings']);Route::post('/api/users',[MasterController::class,'user']);Route::put('/api/users/{id}',[MasterController::class,'user']);
 Route::get('/api/operations/{module}',[OperationsController::class,'index']);Route::post('/api/operations/{module}',[OperationsController::class,'save']);Route::put('/api/operations/{module}/{id}',[OperationsController::class,'save']);Route::post('/api/manufacture/{id}',[OperationsController::class,'manufacture']);Route::post('/api/depreciate/{id}',[OperationsController::class,'depreciate']);
 Route::get('/api/bank',[OperationsController::class,'bank']);Route::post('/api/bank',[OperationsController::class,'bankSave']);Route::post('/api/bank/{id}/reconcile',[OperationsController::class,'reconcile']);
 Route::get('/api/payroll',[PayrollController::class,'index']);Route::post('/api/payroll/calculate',[PayrollController::class,'calculate']);Route::get('/api/payroll/{id}',[PayrollController::class,'slips']);Route::post('/api/payroll/{id}/post',[PayrollController::class,'post']);Route::get('/payslip/{id}/{employee}',[PayrollController::class,'print']);Route::get('/api/attendance',[PayrollController::class,'attendanceList']);Route::post('/api/attendance',[PayrollController::class,'attendance']);
 Route::post('/api/import/preview',[ImportController::class,'preview']);Route::post('/api/import/apply',[ImportController::class,'apply']);
});

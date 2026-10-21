<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,RateLimiter};
use App\Support\Audit;
class AuthController {
 public function login(Request $r){
  $v=$r->validate(['email'=>'required|email|max:255','password'=>'required|string|max:255']);$key='login:'.hash('sha256',strtolower($v['email']).'|'.$r->ip());
  if(RateLimiter::tooManyAttempts($key,5))return back()->withErrors(['email'=>'Too many attempts. Try again shortly.']);
  if(!Auth::attempt($v+['active'=>true])){RateLimiter::hit($key,300);return back()->withErrors(['email'=>'Email or password is incorrect.']);}
  RateLimiter::clear($key);$r->session()->regenerate();Audit::record(Auth::id(),'login','users',Auth::id(),null,null);return redirect('/');
 }
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');}
 public function password(Request $r){$v=$r->validate(['current_password'=>'required|current_password','password'=>'required|string|min:12|max:255|confirmed']);$r->user()->password=$v['password'];$r->user()->save();$r->session()->regenerate();Audit::record($r->user()->id,'password_change','users',$r->user()->id,null,null);return ['ok'=>true];}
}

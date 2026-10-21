<?php
namespace App\Http\Middleware;
use Closure;
class SecureHeaders {
 public function handle($request,Closure $next) {
  if ($request->user() && !$request->user()->active) {\Illuminate\Support\Facades\Auth::logout();$request->session()->invalidate();abort(403);}
  $response=$next($request);
  $response->headers->set('X-Content-Type-Options','nosniff');
  $response->headers->set('X-Frame-Options','DENY');
  $response->headers->set('Referrer-Policy','same-origin');
  $response->headers->set('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
  if ($request->is('api/*') || $request->is('print/*') || $request->path()==='/') $response->headers->set('Cache-Control','no-store, private');
  return $response;
 }
}

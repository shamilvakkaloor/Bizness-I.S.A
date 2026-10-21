<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php',commands: __DIR__.'/../routes/console.php',health: '/up')
 ->withMiddleware(function(Middleware $middleware) {
   $middleware->web(append: [\App\Http\Middleware\SecureHeaders::class]);
   $middleware->redirectGuestsTo('/login');
 })
 ->withExceptions(function(Exceptions $exceptions) {
   $exceptions->shouldRenderJsonWhen(fn($request,$e) => $request->is('api/*') || $request->expectsJson());
 })->create();

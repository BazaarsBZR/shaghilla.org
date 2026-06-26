<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleToEnglish
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('en');

        return $next($request);
    }
}


<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveClinic
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        $clinic = $user->resolvedClinic();

        abort_if(! $clinic, 403, 'El usuario autenticado no tiene una clinica activa asignada.');

        abort_if(
            $clinic->status !== 'active',
            403,
            'La clinica seleccionada esta inactiva. Contacte al administrador.'
        );

        return $next($request);
    }
}

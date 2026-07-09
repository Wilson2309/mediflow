<?php

namespace App\Http\Controllers\Concerns;

trait ResolvesClinic
{
    private function clinicId(): int
    {
        $clinic = auth()->user()?->activeClinic();

        abort_if(! $clinic, 403, 'El usuario autenticado no tiene una clinica activa asignada.');

        return (int) $clinic->id;
    }
}

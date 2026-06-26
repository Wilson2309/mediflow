<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDemoRequestRequest;
use App\Models\DemoRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class PublicDemoRequestController extends Controller
{
    public function store(StoreDemoRequestRequest $request): RedirectResponse
    {
        $success = 'Solicitud enviada correctamente. Nos pondremos en contacto contigo pronto.';

        if ($request->filled('website')) {
            return redirect()->to(url('/').'#contacto')->with('success', $success);
        }

        $validated = $request->safe()->except('website');

        $demoRequest = DemoRequest::create([
            ...$validated,
            'status' => 'pending',
            'source' => 'landing',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        AuditLogger::log('demo_request.created', 'demo_requests', $demoRequest, [], AuditLogger::modelSnapshot($demoRequest), 'Solicitud de demo creada desde landing.');

        return redirect()->to(url('/').'#contacto')->with('success', $success);
    }
}




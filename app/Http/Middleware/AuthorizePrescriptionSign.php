<?php

namespace App\Http\Middleware;

use App\Models\Prescription;
use App\Services\PrescriptionSignAudit;
use App\Support\ControlledResponse;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizePrescriptionSign
{
    public function __construct(
        private readonly PrescriptionSignAudit $signAudit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $prescription = $request->route('prescription');

        abort_unless($prescription instanceof Prescription, 404);

        $decision = Gate::inspect('sign', $prescription);

        if ($decision->denied()) {
            $reason = $this->signAudit->normalizeReason($decision->message());

            $this->signAudit->record(
                request: $request,
                prescription: $prescription,
                result: 'denied',
                reason: $reason,
            );

            if ($reason === 'wrong_clinic') {
                return ControlledResponse::error($request, 404, 'RESOURCE_NOT_FOUND');
            }

            if (in_array($reason, ['already_signed', 'cancelled', 'invalid_status'], true)) {
                if ($request->expectsJson()) {
                    return ControlledResponse::jsonError(409, 'RESOURCE_STATE_CONFLICT');
                }

                $message = $reason === 'already_signed'
                    ? 'La receta ya está firmada.'
                    : 'No se puede firmar una receta cancelada.';

                return back()->with('error', $message);
            }

            return ControlledResponse::error($request, 403, 'OPERATION_NOT_AUTHORIZED');
        }

        $response = $next($request);

        if ($request->expectsJson() && $response->getStatusCode() === 423) {
            return ControlledResponse::jsonError(423, 'RECENT_AUTHENTICATION_REQUIRED');
        }

        if ($response instanceof RedirectResponse
            && $response->getTargetUrl() === route('password.confirm')) {
            $request->session()->put(
                'url.intended',
                route('prescriptions.show', $prescription),
            );
        }

        return $response;
    }
}

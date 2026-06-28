<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAuditController extends Controller
{
    public const FINANCIAL_ACTIONS = [
        'payment.paid',
        'payment.cancelled',
        'payment.refunded',
        'payment.receipt_downloaded',
        'payment.receipt_printed',
        'report.financial_exported_pdf',
        'report.financial_exported_csv',
        'report.financial_printed',
    ];

    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $action = $request->query('action');
        $search = trim((string) $request->query('search'));

        $baseQuery = AuditLog::query()
            ->with('user')
            ->forClinic($clinicId)
            ->whereIn('action', self::FINANCIAL_ACTIONS);

        $logs = (clone $baseQuery)
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when(in_array($action, self::FINANCIAL_ACTIONS, true), fn ($query) => $query->where('action', $action))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('auditable_id', $search);
                });
            })
            ->latestFirst()
            ->paginate(15)
            ->withQueryString();

        return view('financial-audit.index', [
            'logs' => $logs,
            'actions' => self::FINANCIAL_ACTIONS,
            'eventsToday' => (clone $baseQuery)->whereDate('created_at', today(config('app.timezone', 'America/Guayaquil'))->toDateString())->count(),
            'paymentsToday' => (clone $baseQuery)->where('module', 'payments')->whereDate('created_at', today(config('app.timezone', 'America/Guayaquil'))->toDateString())->count(),
            'exportsToday' => (clone $baseQuery)->where('module', 'reports')->whereDate('created_at', today(config('app.timezone', 'America/Guayaquil'))->toDateString())->count(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'action' => $action,
            'search' => $search,
        ]);
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clinica asignada.');

        return (int) $clinicId;
    }
}

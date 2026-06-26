<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $userId = $request->query('user_id');
        $module = $request->query('module');
        $action = $request->query('action');
        $search = trim((string) $request->query('search'));

        $baseQuery = AuditLog::query()->forClinic($clinicId);

        $logs = AuditLog::query()
            ->with(['user', 'clinic'])
            ->forClinic($clinicId)
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($module, fn ($query) => $query->where('module', $module))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('auditable_id', $search);
                });
            })
            ->latestFirst()
            ->paginate(15)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'users' => User::where('clinic_id', $clinicId)->orderBy('name')->get(['id', 'name', 'email']),
            'modules' => (clone $baseQuery)->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => (clone $baseQuery)->distinct()->orderBy('action')->pluck('action'),
            'actionsToday' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            'paymentsToday' => (clone $baseQuery)->where('module', 'payments')->whereDate('created_at', today())->count(),
            'signedPrescriptionsToday' => (clone $baseQuery)->where('action', 'prescription.signed')->whereDate('created_at', today())->count(),
            'activeUsersToday' => (clone $baseQuery)->whereDate('created_at', today())->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'userId' => $userId,
            'module' => $module,
            'action' => $action,
            'search' => $search,
        ]);
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }
}
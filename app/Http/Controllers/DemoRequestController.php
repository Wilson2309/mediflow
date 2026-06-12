<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDemoRequestStatusRequest;
use App\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemoRequestController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $interestModule = $request->query('interest_module');

        $demoRequests = DemoRequest::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists((string) $status, DemoRequest::STATUSES), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists((string) $interestModule, DemoRequest::INTEREST_MODULES), fn ($query) => $query->where('interest_module', $interestModule))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('demo-requests.index', compact('demoRequests', 'search', 'status', 'interestModule'));
    }

    public function show(DemoRequest $demoRequest): View
    {
        return view('demo-requests.show', compact('demoRequest'));
    }

    public function update(UpdateDemoRequestStatusRequest $request, DemoRequest $demoRequest): RedirectResponse
    {
        $data = $request->validated();

        if ($data['status'] === 'contacted' && ! $demoRequest->contacted_at) {
            $data['contacted_at'] = now();
        }

        $demoRequest->update($data);

        return redirect()
            ->route('demo-requests.show', $demoRequest)
            ->with('success', 'Solicitud de demo actualizada correctamente.');
    }
}

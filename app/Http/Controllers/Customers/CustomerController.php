<?php

namespace App\Http\Controllers\Customers;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::where('workspace_id', $request->user()->workspace_id)
            ->when($request->get('search'), fn($q, $s) => $q->search($s))
            ->withCount('conversations')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => $request->only(['search']),
        ]);
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');

        $customers = Customer::where('workspace_id', $request->user()->workspace_id)
            ->where(function ($query) use ($q) {
                $query->where('name', 'ilike', "%{$q}%")
                      ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($customers);
    }

    public function show(Request $request, Customer $customer): Response
    {
        abort_unless($customer->workspace_id === $request->user()->workspace_id, 403);

        $customer->load(['conversations' => fn($q) => $q->latest()->limit(10)]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }
}

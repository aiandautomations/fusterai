<?php

namespace App\Http\Controllers\Customers;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    public function index(Request $request): Response
    {
        $customers = $this->service->paginate(
            $request->user()->workspace_id,
            $request->get('search'),
        );

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => $request->only(['search']),
        ]);
    }

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $customers = $this->service->search(
            $request->user()->workspace_id,
            $request->get('q', ''),
        );

        return response()->json($customers);
    }

    public function show(Request $request, Customer $customer): Response
    {
        abort_unless($customer->workspace_id === $request->user()->workspace_id, 403);

        $customer->load(['conversations' => fn ($q) => $q->with('mailbox')->latest()->limit(10)]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): \Illuminate\Http\JsonResponse
    {
        abort_unless($customer->workspace_id === $request->user()->workspace_id, 403);

        $this->service->update($customer, $request->validated());

        return response()->json(['ok' => true]);
    }
}

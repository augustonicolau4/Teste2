<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 1);

        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->search($request->query('q'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', ['customers' => $customers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'nif'     => ['nullable', 'string', 'max:20'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:100'],
            'type'    => ['nullable', 'string', 'max:20'],
        ]);

        $data['company_id'] = (int) $request->user()->company_id;

        $customer = Customer::query()->create($data);

        return redirect()->route('customers.index')
            ->with('status', "Cliente {$customer->name} registado com sucesso.");
    }
}

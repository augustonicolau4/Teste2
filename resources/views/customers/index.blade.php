@extends('layouts.app')

@section('title', 'Clientes')
@section('heading', 'Clientes e Frotas')

@section('content')
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.') . ' Kz';
    @endphp

    <div x-data="{ showForm: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500">Pesquisar</label>
                    <input name="q" value="{{ request('q') }}" placeholder="Nome, NIF ou telefone"
                           class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filtrar</button>
            </form>

            @if (auth()->user()?->can('customers.manage') || auth()->user()?->isAdmin())
                <button type="button" @click="showForm = true"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    + Novo cliente
                </button>
            @endif
        </div>

        {{-- Formulário --}}
        <div x-cloak x-show="showForm" class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5">
            <form method="POST" action="{{ route('customers.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-500">Nome *</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">NIF</label>
                    <input name="nif" value="{{ old('nif') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Telefone</label>
                    <input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">E-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Endereço</label>
                    <input name="address" value="{{ old('address') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Cidade</label>
                    <input name="city" value="{{ old('city') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Tipo</label>
                    <select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="individual">Particular</option>
                        <option value="company">Empresa</option>
                        <option value="fleet">Frota</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 md:col-span-3">
                    <button class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar cliente</button>
                    <button type="button" @click="showForm = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Fechar</button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Nome</th>
                            <th class="px-5 py-3 font-medium">NIF</th>
                            <th class="px-5 py-3 font-medium">Contacto</th>
                            <th class="px-5 py-3 font-medium">Cidade</th>
                            <th class="px-5 py-3 font-medium">Tipo</th>
                            <th class="px-5 py-3 text-right font-medium">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($customers as $customer)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $customer->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $customer->nif ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $customer->phone ?? '—' }}
                                    @if ($customer->email) <div class="text-xs text-slate-400">{{ $customer->email }}</div> @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ $customer->city ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $customer->isCompany() ? 'Empresa/Frota' : 'Particular' }}</td>
                                <td class="px-5 py-3 text-right font-medium {{ (float) $customer->balance > 0 ? 'text-red-600' : 'text-slate-900' }}">
                                    {{ $kz($customer->balance) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">Sem clientes registados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $customers->links() }}</div>
    </div>
@endsection

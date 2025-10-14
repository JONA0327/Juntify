@extends('layouts.app')

@section('title', 'Historial de Pagos')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Historial de Pagos</h1>
            <p class="text-gray-600 mt-2">Revisa todos tus pagos y suscripciones</p>
        </div>

        <!-- Current Subscription Card -->
        @if($user = auth()->user())
        @php $currentSubscription = $user->getCurrentSubscription() @endphp
        @if($currentSubscription)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Suscripción Actual</h3>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            {{ $currentSubscription->plan->name }}
                        </span>
                        <span class="ml-2 text-sm text-gray-600">
                            @if($user->plan_expires_at)
                                Expira el {{ $user->plan_expires_at->format('d/m/Y') }}
                            @else
                                Activo
                            @endif
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">
                        ${{ number_format($currentSubscription->plan->price, 0, ',', '.') }}
                    </div>
                    <div class="text-sm text-gray-600">por mes</div>
                </div>
            </div>
        </div>
        @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800">Sin Suscripción Activa</h3>
                    <p class="text-yellow-700 mt-1">Estás usando el plan gratuito</p>
                </div>
                <a href="{{ route('subscription.plans') }}"
                   class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors duration-200">
                    Ver Planes
                </a>
            </div>
        </div>
        @endif
        @endif

        <!-- Payments Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Historial de Transacciones</h3>
            </div>

            @if($payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Plan
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Monto
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Método de Pago
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Referencia
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $payment->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $payment->plan->name }}</div>
                                <div class="text-sm text-gray-500">{{ $payment->description }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ${{ number_format($payment->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusClasses = [
                                        'approved' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'in_process' => 'bg-blue-100 text-blue-800',
                                        'authorized' => 'bg-blue-100 text-blue-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                        'refunded' => 'bg-orange-100 text-orange-800',
                                    ];
                                    $statusNames = [
                                        'approved' => 'Aprobado',
                                        'pending' => 'Pendiente',
                                        'in_process' => 'En Proceso',
                                        'authorized' => 'Autorizado',
                                        'rejected' => 'Rechazado',
                                        'cancelled' => 'Cancelado',
                                        'refunded' => 'Reembolsado',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$payment->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusNames[$payment->status] ?? ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $payment->payment_method ? ucfirst(str_replace('_', ' ', $payment->payment_method)) : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                {{ Str::limit($payment->external_reference, 20) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $payments->links() }}
            </div>
            @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay pagos registrados</h3>
                <p class="mt-1 text-sm text-gray-500">Cuando realices tu primer pago, aparecerá aquí.</p>
                <div class="mt-6">
                    <a href="{{ route('subscription.plans') }}"
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Ver Planes Disponibles
                    </a>
                </div>
            </div>
            @endif
        </div>

        <!-- Actions -->
        <div class="mt-8 flex justify-between">
            <a href="{{ route('organization.index') }}"
               class="text-blue-600 hover:text-blue-800 font-medium">
                ← Volver al inicio
            </a>

            <a href="{{ route('subscription.plans') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors duration-200">
                Cambiar Plan
            </a>
        </div>
    </div>
</div>
@endsection

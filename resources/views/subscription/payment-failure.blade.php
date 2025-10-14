@extends('layouts.app')

@section('title', 'Error en el Pago')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <!-- Error Icon -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6">
                <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            <!-- Title -->
            <h1 class="text-3xl font-bold text-gray-900 mb-4">
                Error en el Pago
            </h1>

            <!-- Description -->
            <p class="text-lg text-gray-600 mb-6">
                No pudimos procesar tu pago en este momento
            </p>

            @if($paymentStatus)
            <!-- Payment Details -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <h3 class="font-semibold text-gray-900 mb-3">Detalles del pago:</h3>
                <div class="space-y-2 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>Plan:</span>
                        <span class="font-medium">{{ $paymentStatus['payment']->plan->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Monto:</span>
                        <span class="font-medium">${{ number_format($paymentStatus['payment']->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Estado:</span>
                        <span class="font-medium text-red-600">
                            @switch($paymentStatus['payment']->status)
                                @case('rejected')
                                    Rechazado
                                    @break
                                @case('cancelled')
                                    Cancelado
                                    @break
                                @default
                                    Error
                            @endswitch
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Fecha:</span>
                        <span class="font-medium">{{ $paymentStatus['payment']->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Possible Reasons -->
            <div class="mb-6 text-left">
                <h3 class="font-semibold text-gray-900 mb-2">Posibles causas:</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Fondos insuficientes en tu tarjeta</li>
                    <li>• Datos de tarjeta incorrectos</li>
                    <li>• Límite de compras excedido</li>
                    <li>• Problema temporal con el procesador</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="{{ route('subscription.plans') }}"
                   class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 inline-block">
                    Intentar nuevamente
                </a>

                <button onclick="contactSupport()"
                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-colors duration-200">
                    Contactar soporte
                </button>

                <a href="{{ route('organization.index') }}"
                   class="w-full text-gray-500 hover:text-gray-700 font-medium py-2 px-6 rounded-lg transition-colors duration-200 inline-block">
                    Volver al inicio
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Support Modal -->
<div id="supportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="text-center">
            <h3 class="text-lg font-semibold mb-4">Contactar Soporte</h3>
            <p class="text-gray-600 mb-4">
                Si sigues teniendo problemas con el pago, puedes contactarnos:
            </p>
            <div class="space-y-2 text-sm">
                <p><strong>Email:</strong> soporte@juntify.com</p>
                <p><strong>WhatsApp:</strong> +54 9 11 1234-5678</p>
                <p><strong>Horario:</strong> Lun-Vie 9:00-18:00</p>
            </div>
            <button onclick="closeSupportModal()"
                    class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                Cerrar
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function contactSupport() {
    document.getElementById('supportModal').classList.remove('hidden');
    document.getElementById('supportModal').classList.add('flex');
}

function closeSupportModal() {
    document.getElementById('supportModal').classList.add('hidden');
    document.getElementById('supportModal').classList.remove('flex');
}
</script>
@endsection

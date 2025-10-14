@extends('layouts.app')

@section('title', 'Pago Pendiente')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-yellow-500 to-orange-600 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <!-- Pending Icon -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-yellow-100 mb-6">
                <svg class="h-10 w-10 text-yellow-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>

            <!-- Title -->
            <h1 class="text-3xl font-bold text-gray-900 mb-4">
                Pago en Proceso
            </h1>

            <!-- Description -->
            <p class="text-lg text-gray-600 mb-6">
                Tu pago está siendo procesado
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
                        <span class="font-medium text-yellow-600">
                            @switch($paymentStatus['payment']->status)
                                @case('pending')
                                    Pendiente
                                    @break
                                @case('in_process')
                                    En proceso
                                    @break
                                @case('authorized')
                                    Autorizado
                                    @break
                                @default
                                    Procesando
                            @endswitch
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Referencia:</span>
                        <span class="font-medium text-xs">{{ $paymentStatus['payment']->external_reference }}</span>
                    </div>
                </div>
            </div>

            <!-- Status Indicator -->
            <div class="mb-6">
                <div class="flex items-center justify-center space-x-2">
                    <div class="w-3 h-3 bg-yellow-400 rounded-full animate-pulse"></div>
                    <span class="text-sm text-gray-600">Verificando pago cada 10 segundos...</span>
                </div>
            </div>
            @endif

            <!-- Information -->
            <div class="mb-6 text-left">
                <h3 class="font-semibold text-gray-900 mb-2">¿Qué está pasando?</h3>
                <p class="text-sm text-gray-600 mb-3">
                    Tu pago está siendo verificado por MercadoPago. Esto puede tomar algunos minutos dependiendo del método de pago utilizado.
                </p>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• <strong>Tarjeta de crédito:</strong> 1-2 minutos</li>
                    <li>• <strong>Tarjeta de débito:</strong> 5-10 minutos</li>
                    <li>• <strong>Transferencia:</strong> 1-2 días hábiles</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <button onclick="checkPaymentStatus()"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200"
                        id="checkStatusBtn">
                    Verificar estado ahora
                </button>

                <a href="{{ route('subscription.history') }}"
                   class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-colors duration-200 inline-block">
                    Ver historial de pagos
                </a>

                <a href="{{ route('organization.index') }}"
                   class="w-full text-gray-500 hover:text-gray-700 font-medium py-2 px-6 rounded-lg transition-colors duration-200 inline-block">
                    Continuar usando Juntify
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">¡Pago Aprobado!</h3>
            <p class="text-gray-600 mb-4">Tu suscripción ha sido activada correctamente</p>
            <button onclick="redirectToSuccess()" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg">
                Ver detalles
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let checkInterval;
const externalReference = '{{ $paymentStatus ? $paymentStatus["payment"]->external_reference : "" }}';

// Auto check every 10 seconds
if (externalReference) {
    checkInterval = setInterval(checkPaymentStatus, 10000);
}

function checkPaymentStatus() {
    if (!externalReference) return;

    const btn = document.getElementById('checkStatusBtn');
    btn.disabled = true;
    btn.textContent = 'Verificando...';

    fetch('{{ route("payment.check-status") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            external_reference: externalReference
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.is_approved) {
                // Payment approved
                clearInterval(checkInterval);
                document.getElementById('successModal').classList.remove('hidden');
                document.getElementById('successModal').classList.add('flex');
            } else if (data.is_rejected) {
                // Payment rejected
                clearInterval(checkInterval);
                window.location.href = '{{ route("payment.failure") }}?external_reference=' + externalReference;
            }
            // If still pending, continue checking
        }
    })
    .catch(error => {
        console.error('Error checking payment status:', error);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Verificar estado ahora';
    });
}

function redirectToSuccess() {
    window.location.href = '{{ route("payment.success") }}?external_reference=' + externalReference;
}

// Cleanup interval when leaving page
window.addEventListener('beforeunload', function() {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
});
</script>
@endsection

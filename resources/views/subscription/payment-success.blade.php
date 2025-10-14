@extends('layouts.app')

@section('title', 'Pago Exitoso')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-green-500 to-green-700 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <!-- Success Icon -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
                <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <!-- Title -->
            <h1 class="text-3xl font-bold text-gray-900 mb-4">
                ¡Pago Exitoso!
            </h1>

            <!-- Description -->
            <p class="text-lg text-gray-600 mb-6">
                Tu suscripción ha sido activada correctamente
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
                        <span class="font-medium text-green-600">Aprobado</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Fecha:</span>
                        <span class="font-medium">{{ $paymentStatus['payment']->processed_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Next Steps -->
            <div class="mb-6">
                <h3 class="font-semibold text-gray-900 mb-2">¿Qué sigue?</h3>
                <p class="text-sm text-gray-600">
                    Ya puedes disfrutar de todas las funcionalidades de tu nuevo plan.
                    Tu suscripción estará activa inmediatamente.
                </p>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="{{ route('organization.index') }}"
                   class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 inline-block">
                    Ir a mis organizaciones
                </a>

                <a href="{{ route('subscription.history') }}"
                   class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-colors duration-200 inline-block">
                    Ver historial de pagos
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Auto redirect after 10 seconds -->
<div class="fixed bottom-4 right-4 bg-white rounded-lg shadow-lg p-4 max-w-sm">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-gray-600">
                Redirigiendo en <span id="countdown">10</span> segundos...
            </p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Auto redirect countdown
let countdown = 10;
const countdownElement = document.getElementById('countdown');

const timer = setInterval(function() {
    countdown--;
    countdownElement.textContent = countdown;

    if (countdown <= 0) {
        clearInterval(timer);
        window.location.href = '{{ route("organization.index") }}';
    }
}, 1000);
</script>
@endsection

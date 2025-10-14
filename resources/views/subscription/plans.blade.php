@extends('layouts.app')

@section('title', 'Planes de Suscripción')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-900 via-blue-800 to-purple-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-16">
            <h1 class="text-4xl font-bold text-white mb-4">
                Elige el plan perfecto para tu equipo
            </h1>
            <p class="text-xl text-blue-200">
                Potencia tus reuniones con inteligencia artificial
            </p>
        </div>

        <!-- Billing Toggle -->
        <div class="flex justify-center mb-12">
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 p-2 flex items-center">
                <button id="monthly-btn" class="px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-white bg-blue-600">
                    Mensual
                </button>
                <button id="annual-btn" class="px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-blue-200 hover:text-white">
                    Anual
                </button>
            </div>
            <div class="ml-4 flex items-center text-green-400">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
                <span class="text-sm font-medium">Ahorra 30% con pago anual</span>
            </div>
        </div>

        <!-- Planes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            @foreach($plans as $plan)
            <div class="relative bg-white/10 backdrop-blur-lg rounded-3xl border border-white/20 p-8 text-white hover:bg-white/15 transition-all duration-300
                @if($plan->code === 'basico') ring-2 ring-blue-400 @endif">

                @if($plan->code === 'basico')
                <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                    <span class="bg-blue-500 text-white px-4 py-2 rounded-full text-sm font-semibold">
                        Popular
                    </span>
                </div>
                @endif

                <div class="text-center mb-8">
                    <h3 class="text-2xl font-bold mb-2">{{ $plan->name }}</h3>

                    <!-- Precio mensual -->
                    <div class="monthly-price">
                        <div class="text-4xl font-bold text-blue-300 mb-2">
                            ${{ number_format($plan->price, 0, ',', '.') }}
                        </div>
                        <p class="text-blue-200">por mes</p>
                    </div>

                    <!-- Precio anual (30% descuento) -->
                    <div class="annual-price hidden">
                        @php
                            $annualPrice = $plan->price * 12 * 0.7; // 30% descuento
                            $monthlyEquivalent = $annualPrice / 12;
                        @endphp
                        <div class="text-4xl font-bold text-blue-300 mb-2">
                            ${{ number_format($annualPrice, 0, ',', '.') }}
                        </div>
                        <p class="text-blue-200">por año</p>
                        <div class="mt-2">
                            <span class="text-sm text-green-400 font-medium">
                                Equivale a ${{ number_format($monthlyEquivalent, 0, ',', '.') }}/mes
                            </span>
                        </div>
                        @if($plan->price > 0)
                        <div class="mt-1">
                            <span class="text-xs text-gray-400 line-through">
                                ${{ number_format($plan->price * 12, 0, ',', '.') }}/año
                            </span>
                            <span class="text-xs text-green-400 ml-2 font-semibold">
                                Ahorra ${{ number_format($plan->price * 12 * 0.3, 0, ',', '.') }}
                            </span>
                        </div>
                        @endif
                    </div>
                </div>

                <p class="text-center text-blue-100 mb-8">
                    {{ $plan->description }}
                </p>

                <!-- Features -->
                <div class="space-y-4 mb-8">
                    @foreach($plan->features as $feature)
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm">{{ $feature }}</span>
                    </div>
                    @endforeach
                </div>

                <!-- Button -->
                <div class="text-center">
                    @if($plan->code === 'empresas')
                    <button class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200"
                            onclick="contactSales()">
                        Hablar con ventas
                    </button>
                    @else
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200"
                            onclick="selectPlan({{ $plan->id }}, '{{ $plan->name }}', {{ $plan->price }})"
                            @if($user->getCurrentSubscription()) disabled @endif>
                        @if($user->getCurrentSubscription())
                            Plan Actual
                        @else
                            Actualizar plan
                        @endif
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- Current subscription info -->
        @if($user->getCurrentSubscription())
        <div class="bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 p-6 text-white text-center">
            <h3 class="text-lg font-semibold mb-2">Tu suscripción actual</h3>
            <p class="text-blue-200">
                Plan {{ $user->getCurrentSubscription()->plan->name }} -
                @if($user->plan_expires_at)
                    Expira el {{ $user->plan_expires_at->format('d/m/Y') }}
                @else
                    Activo
                @endif
            </p>
        </div>
        @endif
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold mb-2">Preparando tu pago...</h3>
            <p class="text-gray-600">Te redirigiremos a MercadoPago en un momento</p>
        </div>
    </div>
</div>

<!-- Plan Expired Modal -->
@if(session('plan_expired'))
<div id="planExpiredModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.764 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Plan Expirado</h3>
            <p class="text-gray-600 mb-4">{{ session('plan_expired_message') }}</p>
            <button onclick="closePlanExpiredModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
                Entendido
            </button>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
// Billing Toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const monthlyBtn = document.getElementById('monthly-btn');
    const annualBtn = document.getElementById('annual-btn');
    const monthlyPrices = document.querySelectorAll('.monthly-price');
    const annualPrices = document.querySelectorAll('.annual-price');

    let isAnnual = false;

    monthlyBtn.addEventListener('click', function() {
        if (isAnnual) {
            isAnnual = false;
            monthlyBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-white bg-blue-600';
            annualBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-blue-200 hover:text-white';

            monthlyPrices.forEach(el => el.classList.remove('hidden'));
            annualPrices.forEach(el => el.classList.add('hidden'));
        }
    });

    annualBtn.addEventListener('click', function() {
        if (!isAnnual) {
            isAnnual = true;
            annualBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-white bg-blue-600';
            monthlyBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-blue-200 hover:text-white';

            monthlyPrices.forEach(el => el.classList.add('hidden'));
            annualPrices.forEach(el => el.classList.remove('hidden'));
        }
    });
});

// MercadoPago SDK
const mp = new MercadoPago('{{ config("mercadopago.public_key") }}');

// Seleccionar plan
function selectPlan(planId, planName, price) {
    // Mostrar modal de carga
    document.getElementById('loadingModal').classList.remove('hidden');
    document.getElementById('loadingModal').classList.add('flex');

    // Crear preferencia
    fetch('{{ route("subscription.create-preference") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            plan_id: planId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirigir a MercadoPago
            window.location.href = data.init_point;
        } else {
            alert('Error: ' + data.error);
            document.getElementById('loadingModal').classList.add('hidden');
            document.getElementById('loadingModal').classList.remove('flex');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar el pago');
        document.getElementById('loadingModal').classList.add('hidden');
        document.getElementById('loadingModal').classList.remove('flex');
    });
}

// Contactar ventas para plan empresas
function contactSales() {
    alert('Por favor contacta a nuestro equipo de ventas para el plan Empresas');
}

// Cerrar modal de plan expirado
function closePlanExpiredModal() {
    document.getElementById('planExpiredModal').style.display = 'none';
}
</script>
@endsection

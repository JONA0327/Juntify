<!-- Sección: Planes -->
<div class="content-section" id="section-plans" style="display: none;">
    <div class="subscription-plans-container">
        <div class="plans-header">
            <h2 class="plans-title">Planes de Suscripción</h2>
            <p class="plans-subtitle">Elige el plan perfecto para potenciar tus reuniones</p>
        </div>

        <!-- Planes disponibles -->
        <div class="plans-grid">
            @foreach($plans as $plan)
            <div class="plan-card @if($plan->code === 'basico') popular @endif" data-plan-id="{{ $plan->id }}">
                @if($plan->code === 'basico')
                <div class="plan-badge">Popular</div>
                @endif

                <div class="plan-header">
                    <h3 class="plan-name">{{ $plan->name }}</h3>
                    <div class="plan-price">
                        <span class="currency">$</span>
                        <span class="amount">{{ number_format($plan->price, 0, ',', '.') }}</span>
                        <span class="period">/ mes</span>
                    </div>
                    <p class="plan-description">{{ $plan->description }}</p>
                </div>

                <div class="plan-features">
                    <ul>
                        @foreach($plan->features as $feature)
                        <li>
                            <svg class="feature-check" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            {{ $feature }}
                        </li>
                        @endforeach
                    </ul>
                </div>

                <div class="plan-action">
                    @php
                        $userSubscription = $user->subscriptions()->active()->first();
                        $isCurrentPlan = $userSubscription && $userSubscription->plan_id === $plan->id;
                        $isFree = $plan->code === 'free';
                    @endphp

                    @if($isCurrentPlan)
                        <button class="plan-btn current" disabled>
                            Plan Activo
                        </button>
                    @elseif($isFree)
                        <button class="plan-btn secondary" disabled>
                            Plan Gratuito
                        </button>
                    @else
                        <button class="plan-btn primary" onclick="selectPlan({{ $plan->id }}, '{{ $plan->name }}', {{ $plan->price }})">
                            Seleccionar Plan
                        </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- Estado de suscripción actual -->
        @if($user->subscriptions()->active()->first())
        @php $currentSubscription = $user->subscriptions()->active()->first(); @endphp
        <div class="current-subscription">
            <h3>Tu Suscripción Actual</h3>
            <div class="subscription-info">
                <span class="subscription-plan">{{ $currentSubscription->plan->name }}</span>
                <span class="subscription-status">Activa hasta: {{ $currentSubscription->expires_at->format('d/m/Y') }}</span>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Modal de selección de plan -->
<div id="plan-selection-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirmar Suscripción</h3>
            <button class="modal-close" onclick="closePlanModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="selected-plan-info">
                <h4 id="selected-plan-name"></h4>
                <div class="selected-plan-price">
                    <span>$</span><span id="selected-plan-price"></span><span>/mes</span>
                </div>
            </div>
            <p>¿Deseas proceder con la suscripción a este plan?</p>
        </div>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="closePlanModal()">Cancelar</button>
            <button class="btn-primary" onclick="confirmPlanSelection()">Continuar al Pago</button>
        </div>
    </div>
</div>

<style>
/* Estilos para los planes de suscripción */
.subscription-plans-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.plans-header {
    text-align: center;
    margin-bottom: 3rem;
}

.plans-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 1rem;
}

.plans-subtitle {
    font-size: 1.2rem;
    color: #e2e8f0;
    opacity: 0.8;
}

.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.plan-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    padding: 2rem;
    position: relative;
    transition: all 0.3s ease;
}

.plan-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.15);
}

.plan-card.popular {
    border: 2px solid #3b82f6;
    background: rgba(59, 130, 246, 0.1);
}

.plan-badge {
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    background: #3b82f6;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.plan-header {
    text-align: center;
    margin-bottom: 2rem;
}

.plan-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 1rem;
}

.plan-price {
    display: flex;
    align-items: baseline;
    justify-content: center;
    margin-bottom: 1rem;
}

.plan-price .currency {
    font-size: 1.25rem;
    color: #3b82f6;
}

.plan-price .amount {
    font-size: 3rem;
    font-weight: 700;
    color: #3b82f6;
    margin: 0 0.25rem;
}

.plan-price .period {
    font-size: 1rem;
    color: #e2e8f0;
}

.plan-description {
    color: #e2e8f0;
    font-size: 0.95rem;
    opacity: 0.9;
}

.plan-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.plan-features li {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    color: #ffffff;
    font-size: 0.95rem;
}

.feature-check {
    width: 1.25rem;
    height: 1.25rem;
    color: #10b981;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.plan-action {
    margin-top: 2rem;
}

.plan-btn {
    width: 100%;
    padding: 1rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.plan-btn.primary {
    background: #3b82f6;
    color: white;
}

.plan-btn.primary:hover {
    background: #2563eb;
}

.plan-btn.current {
    background: #10b981;
    color: white;
    cursor: not-allowed;
}

.plan-btn.secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
    cursor: not-allowed;
}

.current-subscription {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
}

.current-subscription h3 {
    color: #10b981;
    margin-bottom: 1rem;
}

.subscription-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #ffffff;
}

/* Modal styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-header h3 {
    color: #ffffff;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: #ffffff;
    font-size: 1.5rem;
    cursor: pointer;
}

.selected-plan-info {
    text-align: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 0.5rem;
}

.selected-plan-info h4 {
    color: #3b82f6;
    margin-bottom: 0.5rem;
}

.selected-plan-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3b82f6;
}

.modal-body p {
    color: #e2e8f0;
    text-align: center;
    margin-bottom: 2rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-secondary, .btn-primary {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

@media (max-width: 768px) {
    .plans-grid {
        grid-template-columns: 1fr;
    }

    .subscription-info {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<script>
let selectedPlanId = null;

function selectPlan(planId, planName, planPrice) {
    selectedPlanId = planId;
    document.getElementById('selected-plan-name').textContent = planName;
    document.getElementById('selected-plan-price').textContent = new Intl.NumberFormat('es-CL').format(planPrice);
    document.getElementById('plan-selection-modal').style.display = 'flex';
}

function closePlanModal() {
    document.getElementById('plan-selection-modal').style.display = 'none';
    selectedPlanId = null;
}

function confirmPlanSelection() {
    if (!selectedPlanId) return;

    console.log('Creating preference for plan ID:', selectedPlanId);
    console.log('Current URL:', window.location.href);
    console.log('Base URL:', window.location.origin);

    const createPreferenceUrl = window.location.origin + '/subscription/create-preference';
    console.log('Request URL:', createPreferenceUrl);

    // Crear preferencia de pago
    fetch(createPreferenceUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            plan_id: selectedPlanId
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('Redirecting to:', data.checkout_url);
            // Redirigir a MercadoPago
            window.location.href = data.checkout_url;
        } else {
            alert('Error al crear la preferencia de pago: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud: ' + error.message);
    })
    .finally(() => {
        closePlanModal();
    });
}

// Cerrar modal al hacer clic fuera
document.getElementById('plan-selection-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePlanModal();
    }
});
</script>

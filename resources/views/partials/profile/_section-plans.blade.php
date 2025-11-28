<!-- Sección: Planes -->
<div class="content-section" id="section-plans" style="display: none;">
    <div class="subscription-plans-container">
        <div class="plans-header">
            <h2 class="plans-title">Planes de Suscripción</h2>
            <p class="plans-subtitle">Elige el plan perfecto para potenciar tus reuniones</p>
        </div>

        <div class="billing-toggle" id="billing-toggle">
            <button type="button" class="toggle-option active" data-billing-period="monthly">Pago mensual</button>
            <button type="button" class="toggle-option" data-billing-period="yearly">Pago anual</button>
        </div>

        <!-- Planes disponibles -->
        <div class="plans-grid">
            @forelse($plans as $plan)
            @php
                $currencySymbols = [
                    'MXN' => '$',
                    'USD' => '$',
                    'EUR' => '€',
                ];
                $currencySymbol = $currencySymbols[$plan->currency] ?? $plan->currency;
                $features = collect($plan->features ?? []);
                $monthlyPrice = $plan->getMonthlyPrice();
                $yearlyBase = $plan->getBaseYearlyPrice();
                $yearlyPrice = $plan->getPriceForPeriod('yearly');
                $isFreePlan = (float) $monthlyPrice === 0.0;
                $hasDiscount = ($plan->discount_percentage ?? 0) > 0;
                $hasFreeMonths = ($plan->free_months ?? 0) > 0;
            @endphp
            <div class="plan-card @if($plan->code === 'basico') popular @endif"
                 data-plan-id="{{ $plan->id }}"
                 data-plan-name="{{ $plan->name }}"
                 data-currency="{{ $plan->currency }}"
                 data-monthly-price="{{ $monthlyPrice }}"
                 data-yearly-price="{{ $yearlyPrice }}"
                 data-yearly-base="{{ $yearlyBase }}"
                 data-discount="{{ $plan->discount_percentage ?? 0 }}"
                 data-free-months="{{ $plan->free_months ?? 0 }}">
                @if($plan->code === 'basico')
                <div class="plan-badge">Popular</div>
                @endif

                <div class="plan-header">
                    <h3 class="plan-name">{{ $plan->name }}</h3>
                    <div class="plan-price">
                        <span class="currency" data-price-currency>{{ $isFreePlan ? '' : $currencySymbol }}</span>
                        <span class="amount @if($isFreePlan) amount-free @endif" data-price-number>
                            @if($isFreePlan)
                                Gratis
                            @else
                                {{ number_format($monthlyPrice, 0, ',', '.') }}
                            @endif
                        </span>
                        <span class="period" data-price-period>{{ $isFreePlan ? '' : '/ mes' }}</span>
                    </div>
                    <p class="plan-description">{{ $plan->description }}</p>
                    <p class="plan-offer @if(!$hasDiscount && !$hasFreeMonths) hidden @endif" data-offer-text>
                        @if($hasDiscount || $hasFreeMonths)
                            Descuento aplicado al anual
                        @endif
                    </p>
                </div>

                <div class="plan-features">
                    <ul>
                        @forelse($features as $feature)
                        <li>
                            <svg class="feature-check" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 01-1.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            {{ $feature }}
                        </li>
                        @empty
                        <li class="feature-empty">Pronto agregaremos más detalles de este plan.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="plan-action">
                    @php
                        // TODO: Implementar verificación de suscripción cuando esté disponible
                        // $userSubscription = $user->subscriptions()->active()->first();
                        // $isCurrentPlan = $userSubscription && $userSubscription->plan_id === $plan->id;
                        $userSubscription = null;
                        $isCurrentPlan = false;
                        $isFree = in_array($plan->code, ['free', 'freemium']);
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
                        <button class="plan-btn primary"
                                data-select-plan
                                data-plan-id="{{ $plan->id }}"
                                data-plan-name="{{ $plan->name }}"
                                data-plan-currency="{{ $plan->currency }}">
                            Seleccionar Plan
                        </button>
                    @endif
                </div>
            </div>
            @empty
            <div class="plan-empty-state">
                <p>No hay planes activos disponibles en este momento. Vuelve a intentarlo más tarde.</p>
            </div>
            @endforelse
        </div>

        <!-- Estado de suscripción actual -->
        @php
            // TODO: Implementar verificación de suscripción cuando esté disponible
            // $currentSubscription = $user->subscriptions()->active()->first();
            $currentSubscription = null;
        @endphp
        @if($currentSubscription)
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
                    <span id="selected-plan-price-currency"></span>
                    <span id="selected-plan-price"></span>
                    <span id="selected-plan-price-period"></span>
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





@php
    $pricing = json_decode(file_get_contents(resource_path('data/pricing.json')), true);
@endphp

<!-- Sección: Planes -->
<div class="content-section" id="section-plans" style="display: none;">
    <div class="pricing-wrapper">
        <div class="pricing-toggle">
            <button class="toggle-btn active" data-target="annual">Anual</button>
            <button class="toggle-btn" data-target="monthly">Mensual</button>
            <button class="toggle-btn" data-target="addons">Reuniones adicionales</button>
        </div>

        <div class="pricing-groups">
            @foreach (['annual', 'monthly', 'addons'] as $group)
                <div class="pricing-grid {{ $group === 'annual' ? '' : 'hidden' }}" data-plan-group="{{ $group }}">
                    @foreach ($pricing[$group] as $plan)
                        @php
                            $ctaLabel = 'Actualizar plan';
                            $ctaClass = '';
                            $disabled = false;
                            if ($group === 'addons') {
                                $ctaLabel = 'Agregar al plan';
                            }
                            if ($plan['id'] === 'freemium' && $group !== 'addons') {
                                $ctaLabel = 'Plan actual';
                                $ctaClass = 'secondary';
                                $disabled = true;
                            }
                            if ($plan['id'] === 'enterprise' && $group !== 'addons') {
                                $ctaLabel = 'Hablar con ventas';
                            }
                        @endphp
                        <div class="pricing-card {{ !empty($plan['popular']) ? 'popular' : '' }}">
                            <h3 class="pricing-title">{{ $plan['name'] }}</h3>
                            <div class="pricing-price">{{ $plan['price'] }}</div>
                            <div class="pricing-period">{{ $plan['period'] }}</div>
                            @if (!empty($plan['description']))
                                <p class="pricing-description">{{ $plan['description'] }}</p>
                            @endif
                            <ul class="pricing-features">
                                @foreach ($plan['features'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="pricing-btn {{ $ctaClass }}" {{ $disabled ? 'disabled' : '' }}>{{ $ctaLabel }}</button>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>

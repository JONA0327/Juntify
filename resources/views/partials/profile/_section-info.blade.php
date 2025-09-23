<!-- Sección: Información del Usuario -->
<div class="content-section" id="section-info">
    <div class="content-grid">
        <div class="info-card">
            <h2 class="card-title">
                <span class="card-icon">👤</span>
                Información Personal
            </h2>
            <div class="info-item">
                <span class="info-label">Nombre de usuario</span>
                <span class="info-value">{{ $user->username }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Nombre completo</span>
                <span class="info-value">{{ $user->full_name }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Correo electrónico</span>
                <span class="info-value">{{ $user->email }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Organización</span>
                <span class="info-value">{{ $user->organization?->nombre_organizacion ?? 'No especificada' }}</span>
            </div>
        </div>

        <div class="info-card">
            <h2 class="card-title">
                <span class="card-icon">💎</span>
                Plan Actual
            </h2>
            @php
                $graceDays = \App\Services\UserPlans\UserPlanService::GRACE_DAYS;
                $now = now();
                $expiresAt = $user->plan_expires_at;
                $graceEndsAt = $expiresAt ? $expiresAt->copy()->addDays($graceDays) : null;
                $isActive = ! $expiresAt || ($graceEndsAt && $graceEndsAt->isFuture());
                $expiringSoon = $expiresAt && $expiresAt->isFuture() && $expiresAt->diffInDays($now) <= $graceDays;
                $withinGrace = $expiresAt && $expiresAt->isPast() && $graceEndsAt && $graceEndsAt->isFuture();
                $downgraded = $expiresAt && $graceEndsAt && $graceEndsAt->isPast() && ($user->roles === 'free');
            @endphp

            @if($expiringSoon)
                <div class="info-alert warning">
                    <strong>Tu plan vence pronto.</strong> Renueva antes del {{ $expiresAt->format('d/m/Y') }} para evitar perder beneficios.
                </div>
            @elseif($withinGrace)
                <div class="info-alert warning">
                    <strong>Tu plan venció el {{ $expiresAt->format('d/m/Y') }}.</strong> Aún tienes {{ $graceEndsAt->diffInDays($now) + 1 }} día(s) de gracia para renovarlo sin perder acceso.
                </div>
            @elseif($downgraded)
                <div class="info-alert danger">
                    <strong>Plan degradado.</strong> Tus beneficios premium finalizaron el {{ $expiresAt->format('d/m/Y') }} y tu cuenta volvió al plan Free.
                </div>
            @endif

            <div class="info-item">
                <span class="info-label">Tipo de plan</span>
                <span class="status-badge status-{{ strtolower($user->roles ?? 'free') }}">
                    {{ ucfirst($user->roles ?? 'free') }}
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Estado</span>
                <span class="status-badge status-{{ $isActive ? 'active' : 'expired' }}">
                    {{ $isActive ? 'Activo' : 'Vencido' }}
                </span>
            </div>
            @if($user->plan_expires_at)
            <div class="info-item">
                <span class="info-label">Expira el</span>
                <span class="info-value">{{ $user->plan_expires_at->format('d/m/Y') }}</span>
            </div>
            @endif
            <div class="info-item">
                <span class="info-label">Miembro desde</span>
                <span class="info-value">{{ $user->created_at->format('d/m/Y') }}</span>
            </div>
        </div>
    </div>
</div>

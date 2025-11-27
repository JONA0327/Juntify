@php
    $user = auth()->user();
    $belongsToAnyGroup = $user && $user->groups()->exists();
    $hasNonGuestRole = $user && $user->groups()->wherePivot('rol', '!=', 'invitado')->exists();
    $canCreate = (!$belongsToAnyGroup) || $hasNonGuestRole;

    // Determinar rutas activas
    $currentRoute = request()->route()->getName();
@endphp

<!-- Barra de navegación móvil -->
<div class="mobile-bottom-nav" id="mobileBottomNav" style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(59, 130, 246, 0.2);
    padding: 0.75rem 1rem;
    display: grid;
    grid-template-columns: 1fr 1fr 80px 1fr 1fr;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.3);
">
    <!-- Reuniones -->
    <div class="nav-item {{ str_contains($currentRoute, 'reuniones') ? 'active' : '' }}"
         onclick="window.location.href='{{ route('reuniones.index') }}'"
         style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.5rem; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;"">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
        </svg>
        <span class="nav-label">Reuniones</span>
    </div>

    <!-- Tareas -->
    <div class="nav-item {{ str_contains($currentRoute, 'tareas') ? 'active' : '' }}" onclick="window.location.href='{{ route('tareas.index') }}'">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="nav-label">Tareas</span>
    </div>

    <!-- Nueva Reunión - Botón Central -->
    @if($canCreate)
    <div class="nav-item nav-center" onclick="window.location.href='{{ route('new-meeting') }}'">
        <div class="nav-center-bg">
            <svg class="nav-icon-center" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </div>
        <span class="nav-label-center">Nueva</span>
    </div>
    @else
    <div class="nav-item nav-center nav-disabled">
        <div class="nav-center-bg disabled">
            <svg class="nav-icon-center" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636" />
            </svg>
        </div>
        <span class="nav-label-center">Bloqueado</span>
    </div>
    @endif

    <!-- Asistente IA -->
    <div class="nav-item {{ str_contains($currentRoute, 'ai-assistant') ? 'active' : '' }}" onclick="window.location.href='{{ route('ai-assistant') }}'">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.091zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
        </svg>
        <span class="nav-label">Asistente</span>
    </div>

    <!-- Más opciones -->
    <div class="nav-item dropdown-trigger" onclick="toggleMobileDropdown()">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
        </svg>
        <span class="nav-label">Más</span>

        <!-- Dropdown -->
        <div class="mobile-dropdown" id="mobile-dropdown">
            <a href="{{ route('contacts.index') }}" class="dropdown-item">
                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                <span class="dropdown-text">Contactos</span>
            </a>

            <a href="{{ route('organization.index') }}" class="dropdown-item">
                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m2.25-18v18m13.5-18v18m2.25-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.75m-.75 3h.75m-.75 3h.75m-3.75 3.75h.75m-.75-16.5h.75" />
                </svg>
                <span class="dropdown-text">Organización</span>
            </a>

            <a href="{{ route('profile.show') }}" class="dropdown-item">
                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <span class="dropdown-text">Perfil</span>
            </a>

            @if(in_array($user?->roles, ['superadmin', 'developer']))
            <a href="{{ route('admin.dashboard') }}" class="dropdown-item">
                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.240.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="dropdown-text">Admin</span>
            </a>
            @endif
        </div>
    </div>
</div>

<!-- Overlay para cerrar el dropdown -->
<div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

<style>
/* Estilos simplificados para navegación móvil */



.nav-item:not(.nav-center) {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    position: relative;
}

.nav-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0 !important;
    border-radius: 0 !important;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    position: relative;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.nav-item:hover:not(.nav-disabled):not(.nav-center) {
    background: rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

.nav-center:hover:not(.nav-disabled) {
    background: transparent !important;
}

.nav-item.active:not(.nav-center) {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.nav-center.active,
.nav-center {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.nav-item.active .nav-icon,
.nav-item.active .nav-label {
    color: #60a5fa;
}

.nav-icon {
    width: 20px;
    height: 20px;
    color: #94a3b8;
    transition: color 0.3s ease;
}

.nav-label {
    font-size: 0.7rem;
    font-weight: 500;
    color: #94a3b8;
    transition: color 0.3s ease;
}

/* Botón central especial - sin estilos de fondo */

.nav-center-bg {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 50% !important;
    -webkit-border-radius: 50% !important;
    -moz-border-radius: 50% !important;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    border: none;
    outline: none;
    overflow: hidden;
    box-sizing: border-box;
    aspect-ratio: 1 / 1;
}

.nav-center-bg.disabled {
    background: linear-gradient(135deg, #64748b, #475569);
    box-shadow: 0 4px 10px rgba(100, 116, 139, 0.3);
}

.nav-center:hover:not(.nav-disabled) .nav-center-bg {
    transform: translateY(-4px) scale(1.1);
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.6);
}

.nav-icon-center {
    width: 24px;
    height: 24px;
    color: white;
}

.nav-label-center {
    font-size: 0.7rem;
    font-weight: 600;
    color: #94a3b8;
    margin-top: 0.25rem;
}

.nav-disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Dropdown */
.dropdown-trigger {
    position: relative;
}

.mobile-dropdown {
    position: absolute;
    bottom: 100%;
    right: 0;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    padding: 0.75rem 0;
    margin-bottom: 0.5rem;
    min-width: 160px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    z-index: 1001;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.dropdown-trigger.active .mobile-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #e2e8f0;
    text-decoration: none;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background: rgba(59, 130, 246, 0.1);
    color: #60a5fa;
}

.dropdown-icon {
    width: 18px;
    height: 18px;
    color: #94a3b8;
}

.dropdown-text {
    font-size: 0.875rem;
    font-weight: 500;
}

.mobile-dropdown-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.mobile-dropdown-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Responsive - Mostrar en móviles */
@media screen and (max-width: 768px) {
    .mobile-bottom-nav {
        display: grid !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    body {
        padding-bottom: 90px !important;
    }

    .main-content,
    .main-admin {
        padding-bottom: 6rem !important;
        margin-bottom: 0 !important;
    }
}

/* Forzar para todos los tamaños móviles */
@media screen and (max-width: 1024px) {
    .mobile-bottom-nav {
        display: grid !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
}

/* Estilo de emergencia - siempre visible en modo responsive */
@media screen and (max-width: 768px) {
    div.mobile-bottom-nav {
        display: grid !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 9999 !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
}
</style>

<script>
function toggleMobileDropdown() {
    const dropdown = document.querySelector('.dropdown-trigger');
    const overlay = document.getElementById('mobile-dropdown-overlay');

    dropdown.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeMobileDropdown() {
    const dropdown = document.querySelector('.dropdown-trigger');
    const overlay = document.getElementById('mobile-dropdown-overlay');

    dropdown.classList.remove('active');
    overlay.classList.remove('active');
}

// Cerrar dropdown al hacer click fuera
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.dropdown-trigger');
    if (dropdown && !dropdown.contains(event.target)) {
        closeMobileDropdown();
    }
});

// Control de visibilidad para móviles
document.addEventListener('DOMContentLoaded', function() {
    function toggleMobileNav() {
        const nav = document.getElementById('mobileBottomNav');
        if (nav) {
            if (window.innerWidth <= 768) {
                nav.style.display = 'grid';
                document.body.style.paddingBottom = '90px';
            } else {
                nav.style.display = 'none';
                document.body.style.paddingBottom = '';
            }
        }
    }

    toggleMobileNav();
    window.addEventListener('resize', toggleMobileNav);
});
</script>

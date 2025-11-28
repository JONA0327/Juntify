@php
    $user = auth()->user();
    $belongsToAnyGroup = $user && $user->groups()->exists();
    $hasNonGuestRole = $user && $user->groups()->wherePivot('rol', '!=', 'invitado')->exists();
    $canCreate = (!$belongsToAnyGroup) || $hasNonGuestRole;
    $currentRoute = request()->route()->getName();
@endphp

<div class="mobile-navbar" id="mobileNavbar">
    <!-- Reuniones -->
    <div class="nav-item {{ str_contains($currentRoute, 'reuniones') ? 'active' : '' }}"
         onclick="window.location.href='{{ route('reuniones.index') }}'">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <span class="nav-label">Reuniones</span>
    </div>

    <!-- Tareas -->
    <div class="nav-item {{ str_contains($currentRoute, 'tareas') ? 'active' : '' }}"
         onclick="window.location.href='{{ route('tareas.index') }}'">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="nav-label">Tareas</span>
    </div>

    <!-- Nueva Reunión - Botón Central -->
    @if($canCreate)
    <div class="nav-item nav-center" onclick="window.location.href='{{ route('new-meeting') }}'">
        <div class="center-button">
            <svg class="center-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                      d="M12 4v16m8-8H4"/>
            </svg>
        </div>
        <span class="center-label">Nueva</span>
    </div>
    @else
    <div class="nav-item nav-center disabled">
        <div class="center-button disabled">
            <svg class="center-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <span class="center-label">Bloqueado</span>
    </div>
    @endif

    <!-- Asistente IA -->
    <div class="nav-item {{ str_contains($currentRoute, 'ai-assistant') ? 'active' : '' }}"
         onclick="window.location.href='{{ route('ai-assistant') }}'">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <span class="nav-label">Asistente</span>
    </div>

    <!-- Más opciones -->
    <div class="nav-item dropdown-container" onclick="toggleMore()">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
        </svg>
        <span class="nav-label">Más</span>

        <!-- Dropdown Menu -->
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="{{ route('contacts.index') }}" class="dropdown-item">
                <svg class="dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>Contactos</span>
            </a>

            <a href="{{ route('organization.index') }}" class="dropdown-item">
                <svg class="dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Organización</span>
            </a>

            <a href="{{ route('profile.show') }}" class="dropdown-item">
                <svg class="dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span>Perfil</span>
            </a>

            @if(in_array($user?->roles, ['superadmin', 'developer']))
            <a href="{{ route('admin.dashboard') }}" class="dropdown-item">
                <svg class="dropdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Admin</span>
            </a>
            @endif
        </div>
    </div>
</div>

<!-- Overlay para cerrar dropdown -->
<div class="dropdown-overlay" id="dropdownOverlay" onclick="closeDropdown()"></div>

<style>
/* Mobile Navigation Bar */
.mobile-navbar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(59, 130, 246, 0.2);
    padding: 12px 16px;
    display: grid;
    grid-template-columns: 1fr 1fr 80px 1fr 1fr;
    align-items: center;
    gap: 8px;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.3);
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 8px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.nav-item:hover:not(.disabled):not(.nav-center) {
    background: rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

.nav-item.active:not(.nav-center) {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.3);
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
    font-size: 11px;
    font-weight: 500;
    color: #94a3b8;
    transition: color 0.3s ease;
}

/* Centro - Nueva Reunión */
.nav-center {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
}

.center-button {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    transition: all 0.3s ease;
}

.center-button.disabled {
    background: linear-gradient(135deg, #64748b, #475569);
    box-shadow: 0 4px 10px rgba(100, 116, 139, 0.3);
}

.nav-center:hover:not(.disabled) .center-button {
    transform: translateY(-4px) scale(1.1);
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.6);
}

.center-icon {
    width: 24px;
    height: 24px;
    color: white;
}

.center-label {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    margin-top: 4px;
}

.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Dropdown */
.dropdown-container {
    position: relative;
}

.dropdown-container:hover {
    background: rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

.dropdown-menu {
    position: absolute;
    bottom: 100%;
    right: 0;
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(20px);
    border: 2px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 8px 0;
    margin-bottom: 8px;
    min-width: 160px;
    display: none;
    z-index: 10001;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.dropdown-item:hover {
    background: rgba(59, 130, 246, 0.1);
    color: #60a5fa;
}

.dropdown-icon {
    width: 18px;
    height: 18px;
    color: #94a3b8;
    flex-shrink: 0;
}

.dropdown-item span {
    font-size: 14px;
    font-weight: 500;
}

.dropdown-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    z-index: 10000;
    display: none;
}

.dropdown-overlay.show {
    display: block;
}

/* Responsive */
@media screen and (max-width: 768px) {
    .mobile-navbar {
        display: grid !important;
    }

    body {
        padding-bottom: 85px !important;
    }
}

@media screen and (min-width: 769px) {
    .mobile-navbar {
        display: none !important;
    }
}
</style>

<script>
// Variables globales
window.mobileDropdownOpen = false;

// Función para toggle del dropdown
window.toggleMore = function() {
    const dropdown = document.getElementById('dropdownMenu');
    const overlay = document.getElementById('dropdownOverlay');

    if (!dropdown || !overlay) return;

    if (window.mobileDropdownOpen) {
        dropdown.classList.remove('show');
        overlay.classList.remove('show');
        window.mobileDropdownOpen = false;
    } else {
        dropdown.classList.add('show');
        overlay.classList.add('show');
        window.mobileDropdownOpen = true;
    }
};

// Función para cerrar dropdown
window.closeDropdown = function() {
    const dropdown = document.getElementById('dropdownMenu');
    const overlay = document.getElementById('dropdownOverlay');

    if (dropdown && overlay) {
        dropdown.classList.remove('show');
        overlay.classList.remove('show');
        window.mobileDropdownOpen = false;
    }
};

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Manejar resize
    function handleResize() {
        const navbar = document.getElementById('mobileNavbar');
        if (!navbar) return;

        if (window.innerWidth <= 768) {
            navbar.style.display = 'grid';
            document.body.style.paddingBottom = '85px';
        } else {
            navbar.style.display = 'none';
            document.body.style.paddingBottom = '';
        }
    }

    handleResize();
    window.addEventListener('resize', handleResize);

    // Cerrar dropdown con overlay
    const overlay = document.getElementById('dropdownOverlay');
    if (overlay) {
        overlay.addEventListener('click', window.closeDropdown);
    }
});
</script>

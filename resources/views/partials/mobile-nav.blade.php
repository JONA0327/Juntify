@php
  // URL base de tu home
  $homeUrl = url('/');
  // ¿estamos en la home sin ancla?
  $isHome = request()->is('/');
@endphp

<!-- Barra de navegación móvil exclusiva -->
<div class="mobile-bottom-nav">
    {{-- Enlace a la página de Reuniones --}}
    <a href="{{ route('reuniones.index') }}" class="nav-item {{ request()->routeIs('reuniones.index') ? 'active' : '' }}">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
        </svg>
        <span class="nav-label">Reuniones</span>
    </a>

    {{-- Enlace a la sección 'Asistente' en la página de inicio --}}
    <a href="{{ $isHome ? '#asistente' : $homeUrl . '#asistente' }}" class="nav-item">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9" cy="12" r="1" fill="currentColor"/>
            <circle cx="15" cy="12" r="1" fill="currentColor"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
        </svg>
        <span class="nav-label">Asistente IA</span>
    </a>

    {{-- Botón central para crear nueva reunión --}}
    <a href="{{ route('new-meeting') }}" class="nav-item nav-center {{ request()->routeIs('new-meeting') ? 'active' : '' }}">
        <svg class="nav-icon-center" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    </a>

    {{-- Enlace a la sección 'Tareas' en la página de inicio --}}
    <a href="{{ $isHome ? '#tareas' : $homeUrl . '#tareas' }}" class="nav-item">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="nav-label">Tareas</span>
    </a>

    {{-- Menú desplegable 'Más' --}}
    <div class="nav-item dropdown-trigger" onclick="toggleMobileDropdown()">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm7.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
        </svg>
        <span class="nav-label">Más</span>
        <div class="mobile-dropdown" id="mobile-dropdown">
            <a href="{{ route('profile.show') }}" class="dropdown-item">
                <x-icon name="user" class="dropdown-icon" />
                <span class="dropdown-text">Perfil</span>
            </a>
            {{-- Enlace a la sección 'Exportar' en la página de inicio --}}
            <a href="{{ $isHome ? '#exportar' : $homeUrl . '#exportar' }}" class="dropdown-item">
                <x-icon name="share" class="dropdown-icon" />
                <span class="dropdown-text">Exportar</span>
            </a>
            <div class="dropdown-item">
                <x-upload-notifications />
            </div>
        </div>
    </div>
</div>

<!-- Overlay para cerrar dropdown -->
<div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

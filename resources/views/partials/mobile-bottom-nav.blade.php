@php
    $user = auth()->user();
    $belongsToAnyGroup = $user && $user->groups()->exists();
    $hasNonGuestRole = $user && $user->groups()->wherePivot('rol', '!=', 'invitado')->exists();
    $canCreate = (!$belongsToAnyGroup) || $hasNonGuestRole;
    $currentRoute = request()->route()->getName();
@endphp

@push('styles')
    @vite('resources/css/mobile-bottom-nav.css')
@endpush

@push('scripts')
    @vite('resources/js/mobile-bottom-nav.js')
@endpush

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

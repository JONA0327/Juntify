<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <button class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Cerrar menú">
        <svg class="icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>
    <div class="sidebar-header">
        <h1 class="logo">Juntify</h1>
        <p class="logo-subtitle">Panel de usuario</p>
    </div>

    <nav class="sidebar-nav" data-tutorial="sidebar">
        <ul>
            <li class="nav-item">
                <a href="#" class="nav-link active" data-section="info" data-tutorial="info-link">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
                    </svg>
                    Información
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-section="connect" data-tutorial="connect-link">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 015.657 5.657l-3.536 3.536a4 4 0 01-5.657 0m-3.536-3.536a4 4 0 015.657-5.657l3.536 3.536" />
                    </svg>
                    Conectar
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-section="plans" data-tutorial="plans-link">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.639 5.033a1 1 0 00.95.69h5.287c.969 0 1.371 1.24.588 1.81l-4.278 3.11a1 1 0 00-.364 1.118l1.64 5.034c.3.921-.755 1.688-1.54 1.118l-4.279-3.11a1 1 0 00-1.175 0l-4.279 3.11c-.784.57-1.838-.197-1.539-1.118l1.639-5.034a1 1 0 00-.364-1.118l-4.278-3.11c-.783-.57-.38-1.81.588-1.81h5.287a1 1 0 00.951-.69l1.639-5.034z" />
                    </svg>
                    Planes
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-section="purchases" data-tutorial="purchases-link">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 2.25h1.386c.51 0 .96.36 1.07.86l.507 2.454a.563.563 0 00.55.436h11.614a.563.563 0 00.551-.436L19.294 3.11a1.125 1.125 0 011.07-.86H21.75M5.25 12.75h14.25m-14.25 0l-.75 3.75m0 0h15.75m-15.75 0L5.25 21m0 0a1.5 1.5 0 103 0m10.5 0a1.5 1.5 0 103 0" />
                    </svg>
                    Mis Compras
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-section="about">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h1.5m-1.5 0v5.25m.75-9.75h.008v.008H12" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                    Acerca de
                </a>
            </li>
            @if(in_array($user->roles, ['superadmin', 'developer']))
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}" onclick="window.location.href='{{ route('admin.dashboard') }}'" class="nav-link {{ request()->routeIs('admin.analyzers') ? 'active' : '' }}">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Administración
                </a>
            </li>
            @endif
        </ul>

        <div class="action-buttons">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn logout-btn">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="version-info">
            Versión <span class="version-number">Juntify 2.0</span>
        </div>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

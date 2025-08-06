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

    <nav class="sidebar-nav">
        <ul>
            {{-- ... otros elementos del menú ... --}}
            <li class="nav-item">
                <a href="#" class="nav-link" data-section="about">
                    <x-icon name="information-circle" class="nav-icon" /> Acerca de
                </a>
            </li>
            @if(in_array($user->roles, ['superadmin', 'developer']))
            <li class="nav-item">
                {{-- ENLACE CORREGIDO --}}
                <a href="{{ route('admin.analyzers') }}" class="nav-link {{ request()->routeIs('admin.analyzers') ? 'active' : '' }}">
                    <x-icon name="cog" class="nav-icon" /> Administración
                </a>
            </li>
            @endif
        </ul>

        <div class="action-buttons">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn logout-btn">
                    <x-icon name="logout" class="nav-icon" /> Cerrar Sesión
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

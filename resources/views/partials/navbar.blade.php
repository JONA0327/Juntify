@php
  // URL base de tu home
  $homeUrl = url('/');
  // URI con la ruta+ancla, p.ej. "/profile" o "/"
  $currentUri = request()->getRequestUri();
  // ¿estamos en la home sin ancla?
  $isHome   = $currentUri === '/';
@endphp

<header class="header">
  <nav class="nav">
    <a href="/" class="logo">Juntify</a>
    
    <!-- Mobile menu button for navbar -->
    <button class="mobile-nav-btn" onclick="toggleMobileNav()" id="mobile-nav-btn">
      <div class="hamburger-nav">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </button>
    
<ul class="nav-links" id="nav-links">
  <li>
    <a href="{{ $isHome
                  ? '#reuniones'
                  : $homeUrl . '#reuniones' }}">
      📅 Reuniones
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? '#nueva-reunion'
                  : $homeUrl . '#nueva-reunion' }}">
      ➕ Nueva Reunión
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? '#tareas'
                  : $homeUrl . '#tareas' }}">
      ✅ Tareas
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? '#exportar'
                  : $homeUrl . '#exportar' }}">
      📤 Exportar
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? '#asistente'
                  : $homeUrl . '#asistente' }}">
      🤖 Asistente IA
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? 'profile'
                  : $homeUrl . '/profile' }}">
      👤 Perfil
    </a>
  </li>
</ul>

  </nav>
</header>

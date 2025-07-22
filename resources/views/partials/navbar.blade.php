@php
  // URL base de tu home
  $homeUrl = url('/');
  // URI con la ruta+ancla, p.ej. "/profile" o "/"
  $currentUri = request()->getRequestUri();
  // ¿estamos en la home sin ancla?
  $isHome   = $currentUri === '/';
  // ¿estamos en nueva reunión?
  $isNewMeeting = request()->routeIs('new-meeting');
@endphp

<header class="header">
  <nav class="nav">
    <a href="/" class="logo">Juntify</a>
    
<ul class="nav-links" id="nav-links">
  <li>
    <a href="{{ $isHome
                  ? '#reuniones'
                  : $homeUrl . '#reuniones' }}">
      📅 Reuniones
    </a>
  </li>
  <li>
    <a href="{{ route('new-meeting') }}" class="{{ $isNewMeeting ? 'active' : '' }}">
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
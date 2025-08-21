@php
  // URL base de tu home
  $homeUrl = url('/');
  // URI con la ruta+ancla, p.ej. "/profile" o "/"
  $currentUri = request()->getRequestUri();
  // ¿estamos en la home sin ancla?
  $isHome   = $currentUri === '/';
  // ¿estamos en nueva reunión?
  $isNewMeeting = request()->routeIs('new-meeting');
  $userRole = auth()->user()?->roles ?? 'free';
@endphp

  <header class="header">
    <nav class="nav">
      <a href="/" class="logo nav-brand">Juntify</a>


<ul class="nav-links" id="nav-links">
  <li>
    <a href="{{ route('reuniones.index') }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
      </svg>
      Reuniones
    </a>
  </li>
  <li>
    <a href="{{ route('new-meeting') }}" class="{{ $isNewMeeting ? 'active' : '' }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
      </svg>
      Nueva Reunión
    </a>
  </li>
  <li>
    <a href="{{ route('tareas.index') }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      Tareas
    </a>
  </li>
  @if(!in_array($userRole, ['free', 'basic']))
  <li>
    <a href="{{ route('organization.index') }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M9 13h6M12 21V3.75a.75.75 0 00-.75-.75h-6A.75.75 0 004.5 3.75V21M19.5 21V10.5a.75.75 0 00-.75-.75H15" />
      </svg>
      Organización
    </a>
  </li>
  @endif
  <li>
    <a href="{{ $isHome
                  ? '#asistente'
                  : $homeUrl . '#asistente' }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="9" cy="12" r="1" fill="currentColor"/>
        <circle cx="15" cy="12" r="1" fill="currentColor"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
      </svg>
      Asistente IA
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? 'profile'
                  : $homeUrl . '/profile' }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
      </svg>
      Perfil
    </a>
  </li>
  <li>
    <x-upload-notifications />
  </li>
  <li>
    <x-invitation-notifications />
  </li>
</ul>

  </nav>
</header>

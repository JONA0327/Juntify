@php
  // URL base de tu home
  $homeUrl = url('/');
  // URI con la ruta+ancla, p.ej. "/profile" o "/"
  $currentUri = request()->getRequestUri();
  // ¿estamos en la home sin ancla?
  $isHome   = $currentUri === '/';
  // ¿estamos en nueva reunión?
  $isNewMeeting = request()->routeIs('new-meeting');
  $hasGroups = auth()->check() && auth()->user()->groups()->exists();
@endphp

  <header class="header hidden lg:block" data-tutorial="navigation">
    <nav class="nav">
      <a href="/" class="logo nav-brand">Juntify</a>


<ul class="nav-links" id="nav-links">
  <li>
    <a href="{{ route('reuniones.index') }}" data-tutorial="meetings-nav">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
      </svg>
      <span class="nav-label">{{ __('navigation.meetings') }}</span>
    </a>
  </li>
  @if(auth()->check() && strtolower(auth()->user()->roles ?? '') === 'bni')
  <li>
    <a href="{{ route('reuniones.bni.index') }}" class="{{ request()->routeIs('reuniones.bni.index') ? 'active' : '' }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
      </svg>
      <span class="nav-label">{{ __('navigation.meetings_bni') }}</span>
    </a>
  </li>
  @endif
  @php
    $user = auth()->user();
    $belongsToAnyGroup = $user && $user->groups()->exists();
    $hasNonGuestRole   = $user && $user->groups()->wherePivot('rol','!=','invitado')->exists();
    // Mostrar "Nueva Reunión" si no pertenece a ningún grupo o si tiene algún rol distinto de invitado
    $canCreate = (!$belongsToAnyGroup) || $hasNonGuestRole;
  @endphp
  @if($canCreate)
  <li>
    <a href="{{ route('new-meeting') }}" class="{{ $isNewMeeting ? 'active' : '' }}" data-tutorial="new-meeting">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="nav-label">{{ __('navigation.new_meeting') }}</span>
    </a>
  </li>
  @endif
  @php
    // Verificar si el usuario tiene acceso a tareas
    $user = auth()->user();
    $userPlan = $user->plan_code ?? 'free';

    // Un usuario pertenece a organización si tiene grupos o roles organizacionales
    $belongsToOrg = $user->groups()->exists() ||
                   in_array($user->roles ?? '', ['admin', 'superadmin', 'founder', 'developer']);

    $hasTasksAccess = $userPlan !== 'free' || $belongsToOrg;
  @endphp
  <li>
    <a href="{{ $hasTasksAccess ? route('tareas.index') : '#' }}"
       @if(!$hasTasksAccess) onclick="event.preventDefault(); showTasksLockedModal();" @endif>
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="nav-label">{{ __('navigation.tasks') }}</span>
    </a>
  </li>
  <li>
    <a href="{{ route('contacts.index') }}" class="{{ request()->routeIs('contacts.index') ? 'active' : '' }}" data-tutorial="contacts">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
      </svg>
      <span class="nav-label">{{ __('navigation.contacts') }}</span>
    </a>
  </li>
  @php /* visible for all users */ @endphp
  <li>
    <a href="{{ route('organization.index') }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
      </svg>
      <span class="nav-label">{{ __('navigation.organization') }}</span>
    </a>
  </li>
  @php /* end */ @endphp
  <li>
    <a href="{{ route('ai-assistant') }}" class="{{ request()->routeIs('ai-assistant') ? 'active' : '' }}" data-tutorial="ai-assistant">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
      </svg>
      <span class="nav-label">{{ __('navigation.ai_assistant') }}</span>
    </a>
  </li>
  <li>
    <a href="{{ $isHome
                  ? 'profile'
                  : $homeUrl . '/profile' }}">
      <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <span class="nav-label">{{ __('navigation.profile') }}</span>
    </a>
  </li>
  <li>
    <x-notifications />
  </li>
</ul>

  </nav>
</header>

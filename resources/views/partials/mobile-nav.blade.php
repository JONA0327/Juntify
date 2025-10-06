@php
  $user = auth()->user();
  $belongsToAnyGroup = $user && $user->groups()->exists();
  $hasNonGuestRole   = $user && $user->groups()->wherePivot('rol','!=','invitado')->exists();
  $canCreate = (!$belongsToAnyGroup) || $hasNonGuestRole;
@endphp

<header id="mobile-header" class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-950/70 backdrop-blur-lg border-b border-slate-800">
    <div class="flex items-center justify-between h-20 px-4 sm:px-6">
        <a href="/" class="flex items-center gap-2">
            <span class="text-xl font-bold text-white">Juntify</span>
        </a>

        <button id="menu-toggle" class="p-2 rounded-md text-slate-300 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-yellow-400">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
        </button>
    </div>
</header>

<div id="mobile-menu" class="hidden lg:hidden fixed inset-0 z-30 bg-slate-950/95 backdrop-blur-lg pt-20">
    <div class="flex flex-col items-center justify-center h-full space-y-6 text-lg">
        <a href="{{ route('reuniones.index') }}" class="text-slate-200 hover:text-yellow-400 flex items-center gap-3">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            Reuniones
        </a>

        @if($canCreate)
        <a href="{{ route('new-meeting') }}" class="text-slate-200 hover:text-yellow-400 flex items-center gap-3">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Nueva Reunión
        </a>
        @endif

        <a href="{{ route('tareas.index') }}" class="text-slate-200 hover:text-yellow-400 flex items-center gap-3">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.25 2.25L15 10.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Tareas
        </a>

        <a href="{{ route('organization.index') }}" class="text-slate-200 hover:text-yellow-400 flex items-center gap-3">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M9 13h6M12 21V3.75a.75.75 0 00-.75-.75h-6A.75.75 0 004.5 3.75V21M19.5 21V10.5a.75.75 0 00-.75-.75H15" />
            </svg>
            Organización
        </a>

        <a href="{{ route('ai-assistant') }}" class="text-slate-200 hover:text-yellow-400 flex items-center gap-3">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <rect x="5" y="7" width="14" height="10" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="12" r="1" fill="currentColor"/>
                <circle cx="15" cy="12" r="1" fill="currentColor"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7V4m-6 6H4m16 0h-2" />
            </svg>
            Asistente IA
        </a>

        <a href="/profile" class="text-slate-200 hover:text-yellow-400 flex items-center gap-3">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
            </svg>
            Perfil
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-slate-400 hover:text-red-400 flex items-center gap-3">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
                Cerrar Sesión
            </button>
        </form>
    </div>
</div>

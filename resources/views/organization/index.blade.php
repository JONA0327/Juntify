<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>Organizaciones - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/organization.css',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ])
    @include('partials.global-vars')
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pt-24 mt-20 sm:mt-24 lg:mt-32 pl-4 pr-4 sm:px-6 lg:pl-24 lg:pr-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="organizationPage(@js($organizations))">
                <div x-show="showAlert" :class="`status-alert ${alertType}`" x-text="alertMessage"></div>
                <!-- Modal de Éxito (dentro del scope de Alpine) -->
                <div x-show="showSuccessModal && successMessage && successMessage.trim() !== ''"
                     @click.self="closeSuccessModal()"
                     @keydown.escape.window="closeSuccessModal()"
                     class="fixed inset-0 bg-black/50 flex items-center justify-center z-[70]" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200 relative z-[71]">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full mb-4"
                                 x-bind:class="isErrorModal ? 'bg-red-500/20' : 'bg-green-500/20'">
                                <template x-if="!isErrorModal">
                                    <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </template>
                                <template x-if="isErrorModal">
                                    <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.22 19h13.56c1.2 0 1.93-1.28 1.34-2.33L13.34 4.66c-.6-1.04-2.08-1.04-2.69 0L3.88 16.67c-.59 1.05.14 2.33 1.34 2.33z"></path>
                                    </svg>
                                </template>
                            </div>
                            <h3 class="text-lg font-semibold mb-2"
                                x-text="isErrorModal ? 'Error' : '¡Éxito!'"
                                x-bind:class="isErrorModal ? 'text-red-400' : 'text-green-400'"></h3>
                            <p class="text-slate-300 mb-6" x-text="successMessage"></p>
                            <div class="flex justify-center space-x-3">
                                <button @click="closeSuccessModal()" class="px-6 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                    Aceptar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Sección de crear/unirse - solo visible cuando no hay organizaciones -->
                <div x-show="organizations.length === 0" class="mb-6 flex flex-col items-center w-full max-w-sm mx-auto space-y-4">
                    <h1 class="text-2xl font-semibold text-center">Organización</h1>
                    @if(!in_array($user->roles, ['free', 'basic']))
                        <button @click="openOrgModal" class="w-full bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Crear organización</button>
                        <div class="flex items-center w-full">
                            <hr class="flex-grow border-slate-700/50">
                            <span class="px-2 text-slate-400">o</span>
                            <hr class="flex-grow border-slate-700/50">
                        </div>
                    @endif
                    <input type="text" x-model="inviteCode" placeholder="Código de invitación" class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                    <button @click="joinOrganization()" :disabled="isJoining" class="w-full bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!isJoining">Unirse</span>
                        <span x-show="isJoining" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Uniéndose...
                        </span>
                    </button>
                </div>

                <!-- Interfaz principal cuando hay organizaciones -->
                <div x-show="organizations.length > 0" class="mb-8">
                    <!-- Título y header -->
                    <div class="mb-8 text-center">
                        <h1 class="text-3xl font-semibold">Gestión de Organización</h1>
                    </div>

                    <!-- Pestañas principales -->
                    <div class="mb-6">
                        <nav class="flex space-x-1 bg-slate-800/30 p-1 rounded-lg max-w-md mx-auto">
                            <button @click="mainTab = 'organization'"
                                    :class="mainTab === 'organization' ? 'bg-yellow-400 text-slate-900' : 'text-slate-400 hover:text-slate-200'"
                                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200">
                                Organización
                            </button>
                            <button @click="mainTab = 'groups'"
                                    :class="mainTab === 'groups' ? 'bg-yellow-400 text-slate-900' : 'text-slate-400 hover:text-slate-200'"
                                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200">
                                Grupos
                            </button>
                            <button @click="mainTab = 'permissions'; loadPermissionsMembers()"
                                    :class="mainTab === 'permissions' ? 'bg-yellow-400 text-slate-900' : 'text-slate-400 hover:text-slate-200'"
                                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200">
                                Permisos
                            </button>
                            <button @click="mainTab = 'activity'"
                                    :class="mainTab === 'activity' ? 'bg-yellow-400 text-slate-900' : 'text-slate-400 hover:text-slate-200'"
                                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200">
                                Actividad
                            </button>
                        </nav>
                    </div>

                    <!-- Contenido de pestañas -->
                    <template x-for="org in organizations" :key="org.id">
                        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-8 shadow-lg">

                            <!-- Pestaña Organización -->
                            <div x-show="mainTab === 'organization'" x-transition>
                                <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between mb-6">
                                    <div class="flex items-center">
                                        <div class="organization-avatar mr-6">
                                            <div class="w-20 h-20 rounded-full border-2 border-slate-600 overflow-hidden bg-slate-700 flex items-center justify-center">
                                                <img x-show="org.imagen" :src="org.imagen" alt="" class="w-full h-full object-cover">
                                                <svg x-show="!org.imagen" class="w-10 h-10 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="text-2xl font-bold text-slate-200 mb-2" x-text="org.nombre_organizacion"></h3>
                                            <p class="text-slate-300 text-lg mb-2" x-text="org.descripcion"></p>
                                            <p class="text-slate-400">Miembros: <span class="font-semibold text-yellow-400" x-text="org.num_miembros"></span></p>
                                        </div>
                                    </div>
                                                                                                            <!-- Acciones y rol (columna derecha unificada) -->
                                            <div class="flex flex-col items-center text-center md:items-end md:text-right">
                                                                                                                <!-- Botones de administración (owner o admin) -->
                                                                                                                <div class="flex space-x-3 items-center" x-show="org.is_owner || org.user_role === 'administrador'">
                                                                                                                    <button @click="openEditOrgModal(org)" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Editar Organización</button>
                                                                                                                    <button x-show="org.is_owner" @click="deleteOrganization(org)" class="bg-red-600 px-4 py-2 rounded-lg font-medium text-white hover:bg-red-700 transition-colors duration-200">Eliminar</button>
                                                                                                                </div>
                                                                                                                <!-- Botón salir (colaborador e invitado) -->
                                                                                                                <div x-show="org.user_role === 'colaborador' || org.user_role === 'invitado'" class="mt-0">
                                                                                                                    <button @click="leaveOrganization()" class="bg-red-600 px-4 py-2 rounded-lg font-medium text-white hover:bg-red-700 transition-colors duration-200">Salir de la organización</button>
                                                                                                                </div>
                                                                                                                <!-- Texto de rol (siempre debajo de los botones) -->
                                                                                                                <p class="mt-3 text-slate-100 text-base md:text-lg font-semibold" x-text="(org.is_owner ? 'Administrador' : (org.user_role ? org.user_role.charAt(0).toUpperCase() + org.user_role.slice(1) : ''))"></p>
                                                                                                            </div>

                                </div>

                                <!-- Estadísticas de la organización -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                                    <div class="bg-slate-700/30 p-4 rounded-lg text-center">
                                        <div class="text-2xl font-bold text-yellow-400" x-text="org.groups ? org.groups.length : 0"></div>
                                        <div class="text-slate-400">Grupos</div>
                                    </div>
                                    <div class="bg-slate-700/30 p-4 rounded-lg text-center">
                                        <div class="text-2xl font-bold text-yellow-400" x-text="org.num_miembros"></div>
                                        <div class="text-slate-400">Miembros Totales</div>
                                    </div>
                                    <div class="bg-slate-700/30 p-4 rounded-lg text-center">
                                        <div class="text-2xl font-bold text-yellow-400" x-text="org.created_at ? new Date(org.created_at).getFullYear() : 'N/A'"></div>
                                        <div class="text-slate-400">Año de Creación</div>
                                    </div>
                                </div>

                                <!-- Gestión de Google Drive -->
                                <div class="mt-8 p-6 bg-slate-700/30 rounded-lg" x-init="loadDriveSubfolders(org)">
                                    <!-- Conectar / Desconectar - solo visible cuando no está conectado -->
                                    <div x-show="!getDriveState(org.id).connected" class="mb-4">
                                        <button @click="connectDrive(org)"
                                                class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                            Conectar Google Drive
                                        </button>
                                    </div>

                                    <!-- Estado de carpeta raíz -->
                                    <div x-show="getDriveState(org.id).connected" class="space-y-4">
                                        <template x-if="getDriveState(org.id).rootFolder">
                                            <div class="flex items-center justify-between">
                                                <p class="text-slate-300">
                                                    Carpeta raíz:
                                                    <span class="font-semibold text-yellow-400" x-text="getDriveState(org.id).rootFolder?.name"></span>
                                                </p>
                                                <button @click="disconnectDrive(org)"
                                                        class="bg-red-600 px-3 py-1.5 rounded-lg font-medium text-white hover:bg-red-700 transition-colors duration-200 text-sm">
                                                    Desconectar Google Drive
                                                </button>
                                            </div>
                                        </template>
                                        <template x-if="!getDriveState(org.id).rootFolder">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-slate-300 mb-2">La organización no tiene una carpeta raíz.</p>
                                                    <button @click="createOrganizationFolder(org)"
                                                            :disabled="getDriveState(org.id).isCreatingRoot"
                                                            class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                                        <span x-show="!getDriveState(org.id).isCreatingRoot">Crear carpeta raíz</span>
                                                        <span x-show="getDriveState(org.id).isCreatingRoot" class="flex items-center justify-center">
                                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                            </svg>
                                                            Creando...
                                                        </span>
                                                    </button>
                                                </div>
                                                <button @click="disconnectDrive(org)"
                                                        class="bg-red-600 px-3 py-1.5 rounded-lg font-medium text-white hover:bg-red-700 transition-colors duration-200 text-sm">
                                                    Desconectar Google Drive
                                                </button>
                                            </div>
                                        </template>

                                        <!-- Sección de subcarpetas reemplazada por explicación de estructura automática -->
                                        <div class="mt-4">
                                            <h4 class="font-semibold mb-3">Estructura de almacenamiento</h4>
                                            <p class="text-slate-400 text-sm leading-relaxed">
                                                Las subcarpetas se gestionan automáticamente. El sistema usa las carpetas fijas <strong>Audios</strong>, <strong>Transcripciones</strong>, <strong>Audios Pospuestos</strong> y <strong>Documentos</strong> dentro de la carpeta raíz de la organización. No necesitas crear subcarpetas manualmente.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pestaña Grupos -->
                            <div x-show="mainTab === 'groups'" x-transition>
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-2xl font-bold text-slate-200">Grupos de la Organización</h3>
                                    @if(!in_array($user->roles, ['free','basic']))
                                    <button x-show="org.user_role !== 'invitado'" @click="openGroupModal(org)" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                        Crear Grupo
                                    </button>
                                    @endif
                                </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-show="Array.isArray(org.groups) && org.groups.length">
                                    <template x-for="group in org.groups" :key="group.id">
                                        <div @click="viewGroup(group)" class="relative bg-slate-700/50 border border-slate-600/50 rounded-lg p-4 hover:bg-slate-700/70 transition-colors duration-200 cursor-pointer">
                                            <!-- Loading overlay -->
                                            <div x-show="isLoadingGroup && currentGroup && currentGroup.id === group.id" class="absolute inset-0 bg-slate-800/80 rounded-lg flex items-center justify-center z-10">
                                                <svg class="animate-spin h-6 w-6 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                </svg>
                                            </div>

                                            <div class="flex items-center justify-between mb-3">
                                                <h4 class="font-semibold text-lg text-slate-200" x-text="group.nombre_grupo"></h4>
                                                <div class="text-yellow-400">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <p class="text-sm text-slate-400 mb-3" x-text="group.descripcion"></p>
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm text-slate-400">
                                                    Miembros: <span class="text-yellow-400 font-medium" x-text="group.miembros || 0"></span>
                                                </p>
<div class="flex space-x-2" x-show="org.is_owner || org.user_role === 'administrador'" @click.stop>
                                                    <button @click="openEditGroupModal(org, group)" class="px-2 py-1 bg-yellow-500 text-slate-900 rounded text-xs hover:bg-yellow-400 transition-colors duration-200">
                                                        Editar
                                                    </button>
                                                    <button @click="openConfirmDeleteGroup(org, group)" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition-colors duration-200">
                                                        Eliminar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="!org.groups || org.groups.length === 0" class="text-center py-8">
                                    <p class="text-slate-400 mb-4">No hay grupos creados en esta organización</p>
                                    <button @click="openGroupModal(org)" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-6 py-3 rounded-lg font-medium shadow-lg hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                        Crear el primer grupo
                                    </button>
                                </div>
                            </div>

                            <!-- Pestaña Permisos -->
                            <div x-show="mainTab === 'permissions'" x-transition>
                                <div class="mb-6">
                                    <h3 class="text-2xl font-bold text-slate-200 mb-2">Gestión de Permisos</h3>
                                    <p class="text-slate-400">Administra los roles y permisos de los miembros en cada grupo</p>
                                </div>

                                <div x-show="org.groups && org.groups.length" class="space-y-6">
                                    <template x-for="group in org.groups" :key="group.id">
                                        <div class="bg-slate-700/30 border border-slate-600/50 rounded-lg p-4">
                                            <h4 class="font-semibold text-lg text-slate-200 mb-1" x-text="group.nombre_grupo"></h4>
                                            <p class="text-sm text-slate-400 mb-4" x-text="group.code ? group.code.code : ''"></p>

                                            <div class="overflow-x-auto">
                                                <table class="w-full border border-slate-600/50 rounded-lg overflow-hidden">
                                                    <thead>
                                                        <tr class="bg-slate-800/50">
                                                            <th class="text-left p-3 text-slate-200">Miembro</th>
                                                            <th class="text-left p-3 text-slate-200">Email</th>
                                                            <th class="text-center p-3 text-slate-200">Rol</th>
<th class="text-center p-3 text-slate-200" x-show="org.is_owner || org.user_role === 'administrador'">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="user in (group.users || [])" :key="user.id">
                                                            <tr class="border-t border-slate-600/50 hover:bg-slate-700/20">
                                                                <td class="p-3 text-slate-200">
                                                                    <div class="font-medium" x-text="user.full_name"></div>
                                                                    <div class="text-sm text-slate-400" x-text="'@' + user.username"></div>
                                                                </td>
                                                                <td class="p-3 text-slate-400" x-text="user.email"></td>
                                                                <td class="p-3 text-center">
                                                                    <select x-model="user.pivot.rol"
                                                                            @change="updateMemberRole(group.id, user)"
                                                                            :disabled="!(org.is_owner || org.user_role === 'administrador')"
                                                                            class="bg-slate-900/50 border border-slate-600/50 rounded p-2 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/50">
                                                                        <option value="invitado">Invitado</option>
                                                                        <option value="colaborador">Colaborador</option>
                                                                        <option value="administrador">Administrador</option>
                                                                    </select>
                                                                </td>
<td class="p-3 text-center" x-show="org.is_owner || org.user_role === 'administrador'">
                                                                    <button @click="openConfirmRemoveMember(user, group.id)"
                                                                            class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700 transition-colors duration-200">
                                                                        Quitar
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                        <template x-if="!group.users || group.users.length === 0">
                                                            <tr>
                                                                <td colspan="4" class="p-4 text-center text-slate-400">No hay miembros en este grupo</td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Botón para invitar miembros al grupo -->
<div class="mt-4" x-show="org.is_owner || org.user_role === 'administrador'">
                                                <button @click="openInviteModal(group)" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium text-sm shadow-lg hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                                    Invitar miembro a este grupo
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="!org.groups || org.groups.length === 0" class="text-center py-8">
                                    <p class="text-slate-400 mb-4">No hay grupos para gestionar permisos</p>
                                    <button @click="mainTab = 'groups'" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-6 py-3 rounded-lg font-medium shadow-lg hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                        Ir a crear grupos
                                    </button>
                                </div>
                            </div>

                            <!-- Pestaña Actividad -->
                            <div x-show="mainTab === 'activity'" x-transition x-init="if (!activities?.[org.id]) loadActivities(org.id)">
                                <h3 class="text-2xl font-bold text-slate-200 mb-4">Actividad reciente</h3>
                                <ul>
                                    <template x-for="activity in activities?.[org.id] ?? []" :key="activity.id">
                                        <li class="py-2 border-b border-slate-700/50">
                                            <span class="text-yellow-400 font-semibold" x-text="activity.actor"></span>
                                            <span class="ml-2" x-text="activity.description"></span>
                                            <span class="ml-2 text-slate-400 text-sm" x-text="activity.created_at"></span>
                                        </li>
                                    </template>
                                    <li x-show="!(activities?.[org.id]?.length)" class="text-slate-400">Sin actividad registrada</li>
                                </ul>
                            </div>

                        </div>
                    </template>
                </div>

                <!-- Modal confirmación eliminar subcarpeta (Drive) -->
                <div x-show="showConfirmDeleteSubfolderModal" @keydown.escape.window="closeConfirmDeleteSubfolder()" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[62]" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-2">Eliminar subcarpeta</h2>
                        <p class="text-slate-300 mb-4">¿Seguro que deseas eliminar la subcarpeta <span class="font-semibold text-yellow-400" x-text="subfolderToDelete?.name"></span>? Esta acción la eliminará de Google Drive y luego de la base de datos.</p>
                        <div class="flex justify-end space-x-2">
                            <button @click="closeConfirmDeleteSubfolder()" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="confirmDeleteSubfolder()" :disabled="isDeletingSubfolder" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 disabled:opacity-50">
                                <span x-show="!isDeletingSubfolder">Eliminar</span>
                                <span x-show="isDeletingSubfolder" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Eliminando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal crear organización -->
                <div x-show="showOrgModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200 relative z-[61]">
                        <h2 class="text-lg font-semibold mb-4">Crear organización</h2>
                        <input type="text" x-model="newOrg.nombre_organizacion" placeholder="Nombre" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="newOrg.descripcion" placeholder="Descripción (opcional)" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>
                        <input type="file" @change="previewImage" class="mb-3 text-slate-200">
                        <template x-if="preview">
                            <img :src="preview" class="w-24 h-24 object-cover rounded-lg mb-3">
                        </template>
                        <div class="flex justify-end space-x-2">
                            <button @click="showOrgModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="createOrganization" :disabled="isCreatingOrg" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!isCreatingOrg">Crear</span>
                                <span x-show="isCreatingOrg" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Creando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal confirmación eliminar grupo -->
                <div x-show="showConfirmDeleteGroupModal" @keydown.escape.window="closeConfirmDeleteGroup()" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[62]" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-2">Confirmar eliminación</h2>
                        <p class="text-slate-300 mb-4">¿Seguro que deseas eliminar el grupo <span class="font-semibold text-yellow-400" x-text="groupToDelete?.nombre_grupo"></span>? Esta acción no se puede deshacer.</p>
                        <div class="flex justify-end space-x-2">
                            <button @click="closeConfirmDeleteGroup()" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="deleteGroup(orgOfGroupToDelete, groupToDelete)" :disabled="isDeletingGroup" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 disabled:opacity-50">
                                <span x-show="!isDeletingGroup">Eliminar</span>
                                <span x-show="isDeletingGroup" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Eliminando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal confirmación expulsar miembro -->
                <div x-show="showConfirmRemoveMemberModal" @keydown.escape.window="closeConfirmRemoveMember()" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[62]" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-2">Confirmar expulsión</h2>
                        <p class="text-slate-300 mb-4">¿Seguro que deseas expulsar a <span class="font-semibold text-yellow-400" x-text="memberToRemove?.full_name"></span>? Esta acción no se puede deshacer.</p>
                        <div class="flex justify-end space-x-2">
                            <button @click="closeConfirmRemoveMember()" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="removeMember()" :disabled="isRemovingMember" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 disabled:opacity-50">
                                <span x-show="!isRemovingMember">Expulsar</span>
                                <span x-show="isRemovingMember" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Expulsando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal crear grupo -->
                <div x-show="showGroupModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Crear grupo</h2>
                        <input type="text" x-model="newGroup.nombre_grupo" placeholder="Nombre del grupo" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="newGroup.descripcion" placeholder="Descripción" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>
                        <div class="flex justify-end space-x-2">
                            <button @click="showGroupModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="createGroup" :disabled="isCreatingGroup" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!isCreatingGroup">Crear</span>
                                <span x-show="isCreatingGroup" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Creando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal invitar a grupo específico -->
                <div x-show="showInviteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-2xl text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Invitar miembro</h2>
                        <p class="text-sm text-slate-400 mb-4" x-text="'Grupo: ' + (selectedGroup?.nombre_grupo || '')"></p>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Por correo</h3>
                                <input type="email"
                                       x-model="inviteEmail"
                                       @input="checkUserExists()"
                                       placeholder="Correo electrónico"
                                       class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">

                                <!-- Mensaje de estado del usuario -->
                                <div x-show="userExistsMessage" class="mb-3 p-2 rounded-lg text-sm font-medium"
                                     :class="userExists ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'">
                                    <div class="flex items-center">
                                        <template x-if="userExists">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </template>
                                        <template x-if="!userExists">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        </template>
                                        <span x-text="userExistsMessage"></span>
                                    </div>
                                </div>

                                <!-- Selector de rol -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Rol en el grupo</label>
                                    <select x-model="inviteRole" class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-yellow-400/50">
                                        <option value="invitado">Invitado</option>
                                        <option value="colaborador">Colaborador</option>
                                        <option value="administrador">Administrador</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Columna contactos -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Tus contactos</h3>
                                    <button @click="loadInvitableContacts()" class="text-xs px-2 py-1 rounded bg-slate-800/60 border border-slate-700/50 hover:bg-slate-700/60">Recargar</button>
                                </div>
                                <input type="text" x-model="inviteContactSearch" placeholder="Buscar contacto..." class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/30 focus:border-yellow-400/30">

                                <div class="h-48 overflow-y-auto rounded-lg border border-slate-700/50 bg-slate-900/30 divide-y divide-slate-700/40 text-sm">
                                    <template x-if="isLoadingInvitableContacts">
                                        <div class="p-3 text-slate-400 flex items-center space-x-2">
                                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" stroke-width="4" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8z"/></svg>
                                            <span>Cargando contactos...</span>
                                        </div>
                                    </template>
                                    <template x-if="invitableContactsError">
                                        <div class="p-3 text-red-400" x-text="invitableContactsError"></div>
                                    </template>
                                    <template x-if="!isLoadingInvitableContacts && !invitableContactsError && filteredInvitableContacts.length === 0">
                                        <div class="p-3 text-slate-500">No hay contactos disponibles</div>
                                    </template>
                                    <template x-for="contact in filteredInvitableContacts" :key="contact.id">
                                        <button type="button" @click="selectInvitableContact(contact)"
                                                class="w-full text-left px-3 py-2 flex items-center justify-between hover:bg-slate-700/40 focus:outline-none focus:bg-slate-700/50 transition"
                                                :disabled="contact.blocked"
                                                :class="{'opacity-50 cursor-not-allowed': contact.blocked}">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 flex items-center justify-center text-[11px] font-semibold text-slate-200" x-text="contact.name.substring(0,2).toUpperCase()"></div>
                                                <div>
                                                    <div class="font-medium text-slate-200" x-text="contact.name"></div>
                                                    <div class="text-xs text-slate-400" x-text="contact.email"></div>
                                                </div>
                                            </div>
                                            <div class="text-xs" :class="contact.blocked ? 'text-red-400' : 'text-yellow-400'">
                                                <span x-show="contact.blocked">Bloqueado</span>
                                                <span x-show="!contact.blocked">Invitar</span>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-relaxed">Selecciona un contacto para autocompletar el correo. Los contactos marcados como bloqueados pertenecen a otra organización.</p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button @click="showInviteModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="sendGroupInvitation()"
                                    :disabled="!inviteEmail || isSendingInvitation"
                                    :class="{'opacity-50 cursor-not-allowed': !inviteEmail || isSendingInvitation}"
                                    class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 flex items-center">
                                <svg x-show="isSendingInvitation" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isSendingInvitation ? 'Enviando...' : (userExists ? 'Enviar Notificación' : 'Enviar Invitación')"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal crear contenedor -->
                <div x-show="showCreateContainerModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Crear Contenedor</h2>
                        <input type="text" x-model="newContainer.name" placeholder="Nombre del contenedor" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="newContainer.description" placeholder="Descripción del contenedor" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>
                        <div class="flex justify-end space-x-2">
                            <button @click="showCreateContainerModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="createContainer" :disabled="isCreatingContainer" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!isCreatingContainer">Crear</span>
                                <span x-show="isCreatingContainer" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Creando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal editar contenedor -->
                <div x-show="showEditContainerModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Editar Contenedor</h2>
                        <input type="text" x-model="editContainer.name" placeholder="Nombre del contenedor" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="editContainer.description" placeholder="Descripción del contenedor" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>
                        <div class="flex justify-end space-x-2">
                            <button @click="showEditContainerModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="saveContainer" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal editar organización -->
                <div x-show="showEditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Editar organización</h2>
                        <input type="text" x-model="editForm.nombre_organizacion" placeholder="Nombre" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="editForm.descripcion" placeholder="Descripción (opcional)" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>

                        <!-- Selector de imagen -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-300 mb-2">Imagen de la organización</label>

                            <!-- Vista previa de imagen actual -->
                            <div x-show="editForm.imagen || editForm.newImagePreview" class="mb-3 text-center">
                                <div class="inline-block relative">
                                    <img :src="editForm.newImagePreview || editForm.imagen"
                                         alt="Imagen de organización"
                                         class="w-24 h-24 object-cover rounded-lg border-2 border-slate-600">
                                    <div x-show="editForm.newImagePreview"
                                         class="absolute -top-2 -right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                                        Nueva
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 mt-1" x-text="editForm.newImagePreview ? 'Nueva imagen seleccionada' : 'Imagen actual'"></p>
                            </div>

                            <!-- Input de archivo -->
                            <input type="file"
                                   id="orgImageInput"
                                   accept="image/*"
                                   @change="handleImageChange($event)"
                                   class="hidden">

                            <!-- Botón para seleccionar imagen -->
                            <button type="button"
                                    @click="document.getElementById('orgImageInput').click()"
                                    class="w-full p-3 bg-slate-800/50 border-2 border-dashed border-slate-600 rounded-lg hover:border-yellow-400/50 transition-colors duration-200 text-center">
                                <svg class="w-6 h-6 mx-auto mb-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span class="text-sm text-slate-300">Seleccionar imagen</span>
                                <span class="block text-xs text-slate-500 mt-1">JPG, PNG, GIF hasta 5MB</span>
                            </button>

                            <!-- Botón para quitar imagen -->
                            <button type="button"
                                    x-show="editForm.imagen || editForm.newImagePreview"
                                    @click="removeImage()"
                                    class="w-full mt-2 p-2 text-red-400 hover:text-red-300 text-sm transition-colors duration-200">
                                Quitar imagen
                            </button>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button @click="showEditModal=false; editForm.newImagePreview=null; editForm.newImageFile=null" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="saveOrganization" :disabled="isSavingOrganization" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                <svg x-show="isSavingOrganization" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isSavingOrganization ? 'Guardando...' : 'Guardar'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal editar grupo -->
                <div x-show="showEditGroupModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Editar grupo</h2>
                        <input type="text" x-model="editGroupForm.nombre_grupo" placeholder="Nombre del grupo" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="editGroupForm.descripcion" placeholder="Descripción" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>
                        <div class="flex justify-end space-x-2">
                            <button @click="showEditGroupModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="saveGroup" :disabled="isSavingGroup" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                <svg x-show="isSavingGroup" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isSavingGroup ? 'Guardando...' : 'Guardar'"></span>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Modal ver reuniones del contenedor -->
                <div x-show="showContainerMeetingsModal" id="container-meetings-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[75]" x-cloak>
                    <div class="bg-slate-950 rounded-xl border border-slate-700/50 shadow-2xl shadow-black/20 w-full max-w-6xl max-h-[90vh] overflow-hidden">
                        <!-- Header del modal -->
                        <div class="flex items-center justify-between p-6 border-b border-slate-700/50">
                            <div>
                                <h2 class="text-2xl font-bold text-white" x-text="selectedContainer?.name || 'Contenedor'"></h2>
                                <p class="text-slate-400 mt-1" x-text="selectedContainer?.description || 'Reuniones del contenedor'"></p>
                            </div>
                            <button @click="showContainerMeetingsModal = false" class="text-slate-400 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Contenido del modal -->
                        <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                            <!-- Loading state mientras se cargan las reuniones -->
                            <div x-show="selectedContainer && selectedContainer._isLoading" class="flex justify-center items-center py-20">
                                <svg class="animate-spin h-8 w-8 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span class="ml-3 text-slate-300">Cargando reuniones...</span>
                            </div>

                            <!-- Lista de reuniones -->
                            <template x-if="!selectedContainer?._isLoading && selectedContainer?.meetings?.length > 0">
                                <div class="meetings-grid">
                                    <template x-for="meeting in selectedContainer.meetings" :key="meeting.id">
                                        <div>
                                            <div x-html="createOrgContainerMeetingCard(meeting)"></div>
                                            <p x-show="!meeting.has_transcript" class="text-xs text-slate-400 mt-2">Transcripción no disponible</p>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Estado vacío -->
                            <template x-if="!selectedContainer?._isLoading && (!selectedContainer?.meetings || selectedContainer.meetings.length === 0)">
                                <div class="text-center py-16">
                                    <div class="mx-auto w-24 h-24 bg-slate-800/30 rounded-full flex items-center justify-center mb-6">
                                        <svg class="w-12 h-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-xl font-semibold text-slate-300 mb-2">No hay reuniones</h3>
                                    <p class="text-slate-400 max-w-md mx-auto">Este contenedor no tiene reuniones asociadas aún. Las reuniones aparecerán aquí cuando sean agregadas al contenedor.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Modal información del grupo -->
                <div @click.self="showGroupInfoModal=false" @keydown.escape.window="showGroupInfoModal=false" class="fixed inset-0 bg-black/50 items-center justify-center z-[55]" :class="showGroupInfoModal ? 'flex' : 'hidden'" x-cloak x-transition.opacity>
                    <div class="organization-modal p-6 w-full max-w-4xl text-slate-200" @click.stop>
                        <!-- Header del modal -->
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-white" x-text="currentGroup?.nombre_grupo || 'Grupo'"></h2>
                                <p class="text-slate-400 mt-1" x-text="currentGroup?.descripcion || 'Información del grupo'"></p>
                            </div>
                            <button @click="showGroupInfoModal = false" class="text-slate-400 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Loading state -->
                        <div x-show="isLoadingGroup" class="flex justify-center items-center py-20">
                            <svg class="animate-spin h-8 w-8 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </div>

                        <!-- Contenido principal -->
                        <div x-show="!isLoadingGroup">
                            <!-- Botón crear contenedor -->
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-semibold">Contenedores</h3>
                                <button x-show="canManageContainers()" @click="openCreateContainerModal()" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                    Crear Contenedor
                                </button>
                            </div>

                            <!-- Lista de contenedores -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-for="container in currentGroup?.containers || []" :key="container.id">
                                    <div @click="viewContainerMeetings(container)" role="button" class="bg-slate-800/50 border border-slate-700/50 rounded-lg p-4 hover:bg-slate-700/50 transition-colors cursor-pointer group relative">
                                        <h4 class="font-semibold text-yellow-400 mb-2">
                                            <span x-text="container.name"></span>
                                            <span class="company-badge ml-2" x-show="container.group_name" x-text="container.group_name"></span>
                                        </h4>
                                        <p class="text-sm text-slate-300 mb-3" x-text="container.description"></p>
                                        <div class="flex justify-between items-center text-xs text-slate-400 mb-3">
                                            <span x-text="'Reuniones: ' + (container.meetings_count || 0)"></span>
                                            <span x-text="formatDate(container.created_at)"></span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button @click.stop="editContainer(container)" class="px-3 py-1 bg-yellow-600 text-white rounded text-xs hover:bg-yellow-700 transition-colors">Editar</button>
                                            <button x-show="canManageContainers()" @click.stop="deleteContainer(container)" class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition-colors">Eliminar</button>
                                            <button @click.stop="openUploadDocument(container)" class="px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition-colors">Subir Doc</button>
                                            <button @click.stop="openContainerDocuments(container)" class="px-3 py-1 bg-slate-600 text-white rounded text-xs hover:bg-slate-500 transition-colors">Ver Docs</button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Estado vacío -->
                            <div x-show="!currentGroup?.containers || currentGroup.containers.length === 0" class="text-center py-16">
                                <div class="mx-auto w-16 h-16 bg-slate-800/30 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-300 mb-2">No hay contenedores</h3>
                                <p class="text-slate-400 mb-4">Los contenedores te permiten organizar las reuniones por categorías</p>
                                <button x-show="canManageContainers()" @click="openCreateContainerModal()" class="px-6 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                    Crear primer contenedor
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modales (reposicionados dentro del scope Alpine) -->
                <!-- Modal Documentos del Contenedor -->
                <div x-show="showContainerDocsModal" x-transition.opacity class="fixed inset-0 bg-black/60 z-[90] flex items-center justify-center p-4" x-cloak>
                    <div class="bg-slate-900 w-full max-w-2xl rounded-lg border border-slate-700 shadow-xl flex flex-col max-h-[80vh] relative">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Documentos del Contenedor</h3>
                                <p class="text-xs text-slate-400 mt-1" x-text="containerDocs.container ? containerDocs.container.name : ''"></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="containerDocs.container && openUploadDocument(containerDocs.container)" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded text-xs disabled:opacity-50 flex items-center gap-1" :disabled="uploadingDocument">
                                    <svg x-show="!uploadingDocument" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12V4m0 8l-3.5-3.5M12 12l3.5-3.5" /></svg>
                                    <svg x-show="uploadingDocument" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                    <span x-text="uploadingDocument ? 'Subiendo...' : 'Subir'"></span>
                                </button>
                                <button @click="closeContainerDocsModal()" class="text-slate-400 hover:text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scrollbar px-5 py-4 space-y-3" x-show="!containerDocs.loading">
                            <template x-if="containerDocs.files.length === 0">
                                <div class="text-center py-12 text-slate-500 text-sm">No hay documentos</div>
                            </template>
                            <template x-for="file in containerDocs.files" :key="file.id">
                                <div class="flex items-center justify-between bg-slate-800/60 border border-slate-700/50 rounded-md px-3 py-2">
                                    <div class="min-w-0 mr-4">
                                        <p class="text-sm font-medium text-slate-200 truncate" x-text="file.name"></p>
                                        <p class="text-[11px] text-slate-400" x-text="formatFileMeta(file)"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a :href="file.url" target="_blank" class="px-2 py-1 text-xs bg-slate-600 hover:bg-slate-500 text-white rounded">Descargar</a>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="containerDocs.loading" class="flex-1 flex items-center justify-center py-16">
                            <div class="flex flex-col items-center">
                                <div class="w-8 h-8 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin mb-4"></div>
                                <p class="text-slate-400 text-sm">Cargando documentos...</p>
                            </div>
                        </div>
                        <div x-show="uploadingDocument" x-transition.opacity class="absolute inset-0 bg-slate-900/80 backdrop-blur flex flex-col items-center justify-center gap-4" style="display:none;">
                            <div class="w-12 h-12 border-4 border-blue-500/30 border-t-blue-400 rounded-full animate-spin"></div>
                            <p class="text-sm text-slate-300">Subiendo documento...</p>
                        </div>
                    </div>
                </div>
                <!-- Modal Subir Documento -->
                <div x-show="showUploadDocModal" x-transition.opacity class="fixed inset-0 bg-black/70 z-[130] flex items-center justify-center p-4" x-cloak>
                    <div class="bg-slate-900 w-full max-w-lg rounded-xl border border-slate-700 shadow-2xl flex flex-col relative overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
                            <div>
                                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12V4m0 8l-3.5-3.5M12 12l3.5-3.5" /></svg>
                                    Subir Documento
                                </h3>
                                <p class="text-xs text-slate-400 mt-1" x-text="uploadTargetContainer ? uploadTargetContainer.name : ''"></p>
                            </div>
                            <button @click="closeUploadDocModal()" class="text-slate-400 hover:text-white" :disabled="uploadingDocument" :class="uploadingDocument ? 'opacity-50 cursor-not-allowed' : ''">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="p-6 flex-1">
                            <div @dragenter.prevent="dragActive = true"
                                 @dragover.prevent="dragActive = true"
                                 @dragleave.prevent="dragActive = false"
                                 @drop.prevent="handleDrop($event)"
                                 :class="['relative border-2 border-dashed rounded-lg flex flex-col items-center justify-center text-center p-10 transition-colors', dragActive ? 'border-blue-400 bg-blue-500/10' : 'border-slate-600 bg-slate-800/40']">
                                <template x-if="!selectedUploadFile">
                                    <div class="space-y-4">
                                        <svg class="w-12 h-12 mx-auto text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4a1 1 0 011-1h8a1 1 0 011 1v12m-4-6l-3 3m0 0l3 3m-3-3h12" /></svg>
                                        <div class="text-slate-300 text-sm font-medium">Arrastra el archivo aquí</div>
                                        <div class="text-slate-500 text-xs">o</div>
                                        <button type="button" @click="triggerFileSelect()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded text-xs font-medium">Seleccionar archivo</button>
                                        <p class="text-[11px] text-slate-500">Máx 150MB</p>
                                    </div>
                                </template>
                                <template x-if="selectedUploadFile">
                                    <div class="w-full">
                                        <p class="text-sm text-slate-200 font-medium truncate" x-text="selectedUploadFile.name"></p>
                                        <p class="text-xs text-slate-400 mt-1" x-text="formatBytes(selectedUploadFile.size)"></p>
                                        <div class="mt-4 w-full bg-slate-700 rounded h-2 overflow-hidden">
                                            <div class="h-2 bg-blue-500 transition-all" :style="'width:' + uploadProgress + '%'"></div>
                                        </div>
                                        <div class="flex items-center justify-between mt-4">
                                            <button type="button" @click="resetSelectedFile()" class="text-xs text-slate-400 hover:text-slate-200" :disabled="uploadingDocument">Cambiar archivo</button>
                                            <button type="button" @click="startUpload()" class="px-4 py-2 rounded text-xs font-semibold flex items-center gap-2" :class="uploadingDocument ? 'bg-blue-700 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-500'" :disabled="uploadingDocument">
                                                <svg x-show="!uploadingDocument" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12V4m0 8l-3.5-3.5M12 12l3.5-3.5" /></svg>
                                                <svg x-show="uploadingDocument" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                <span x-text="uploadingDocument ? 'Subiendo...' : 'Subir' "></span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <input id="hidden-upload-input" type="file" class="hidden" @change="onFileChosen($event)" />
                            </div>
                        </div>
                        <div class="px-5 py-3 border-t border-slate-700 flex justify-end bg-slate-800/40">
                            <button @click="closeUploadDocModal()" class="px-3 py-1.5 text-xs rounded bg-slate-600 hover:bg-slate-500 text-white disabled:opacity-50" :disabled="uploadingDocument">Cerrar</button>
                        </div>
                        <div x-show="uploadingDocument" class="absolute inset-0 bg-slate-900/60 backdrop-blur flex items-center justify-center" style="display:none;">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-10 h-10 border-4 border-blue-500/30 border-t-blue-400 rounded-full animate-spin"></div>
                                <p class="text-xs text-slate-300" x-text="'Subiendo ('+uploadProgress+'%)' "></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<!-- Modal para ver reunión -->
<div id="meeting-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[80] hidden">
    <div class="bg-slate-900 rounded-lg w-full max-w-4xl max-h-[80vh] overflow-hidden border border-slate-700">
        <!-- Header del modal -->
        <div class="flex items-center justify-between p-6 border-b border-slate-700">
            <div>
                <h2 id="meeting-modal-title" class="text-2xl font-bold text-white"></h2>
                <p id="meeting-modal-date" class="text-slate-400 mt-1"></p>
            </div>
            <button onclick="closeMeetingModal()" class="text-slate-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Contenido del modal -->
        <div class="p-6 overflow-y-auto max-h-[calc(80vh-120px)]">
            <!-- Loading state -->
            <div id="meeting-modal-loading" class="flex justify-center items-center py-20">
                <svg class="animate-spin h-8 w-8 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>

            <!-- Error state -->
            <div id="meeting-modal-error" class="hidden text-center py-20">
                <div class="mx-auto w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-red-400 mb-2">Error al cargar la reunión</h3>
                <p id="meeting-modal-error-text" class="text-slate-400"></p>
            </div>

            <!-- Contenido de la reunión -->
            <div id="meeting-modal-content" class="hidden space-y-6">
                <!-- Audio player -->
                <div id="meeting-audio-section" class="bg-slate-800/50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M6 10l6-6 6 6M6 14l6 6 6-6" />
                        </svg>
                        Audio de la reunión
                    </h3>
                    <audio id="meeting-audio-player" controls class="w-full">
                        Tu navegador no soporta el elemento de audio.
                    </audio>
                </div>

                <!-- Resumen -->
                <div class="bg-slate-800/50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Resumen
                    </h3>
                    <p id="meeting-summary" class="text-slate-300 leading-relaxed"></p>
                </div>

                <!-- Puntos claves -->
                <div class="bg-slate-800/50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        Puntos Claves
                    </h3>
                    <ul id="meeting-keypoints" class="space-y-2 text-slate-300"></ul>
                </div>

                <!-- Transcripción -->
                <div class="bg-slate-800/50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        Transcripción
                    </h3>
                    <div id="meeting-transcription" class="space-y-3 max-h-64 overflow-y-auto"></div>
                </div>

                <!-- Tareas -->
                <div id="meeting-tasks-section" class="bg-slate-800/50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Tareas
                    </h3>
                    <div id="meeting-tasks" class="space-y-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función para cerrar modal de reunión
function closeMeetingModal() {
    const modal = document.getElementById('meeting-modal');
    modal.classList.add('hidden');
    const audioPlayer = document.getElementById('meeting-audio-player');
    if (audioPlayer) {
        audioPlayer.pause();
    }
}

// Función para abrir modal de reunión
function openMeetingModal(meetingId) {
    if (!meetingId) {
        console.error('ID de reunión no válido');
        return;
    }

    const modal = document.getElementById('meeting-modal');
    const loadingEl = document.getElementById('meeting-modal-loading');
    const errorEl = document.getElementById('meeting-modal-error');
    const contentEl = document.getElementById('meeting-modal-content');

    // Mostrar modal y loading
    modal.classList.remove('hidden');
    loadingEl.classList.remove('hidden');
    errorEl.classList.add('hidden');
    contentEl.classList.add('hidden');

    // Realizar petición para obtener datos de la reunión
    fetch(`/api/meetings/${meetingId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            loadingEl.classList.add('hidden');

            if (!data.success || !data.meeting) {
                throw new Error(data.message || data.error || 'Error al cargar reunión');
            }

            const meeting = data.meeting;

            // Actualizar título y fecha
            document.getElementById('meeting-modal-title').textContent = meeting.meeting_name || 'Reunión sin título';
            document.getElementById('meeting-modal-date').textContent = meeting.created_at || '';

            // Configurar audio con fallback a endpoint de streaming
            const audioSection = document.getElementById('meeting-audio-section');
            const audioPlayer = document.getElementById('meeting-audio-player');
            // Limpiar estado previo
            audioPlayer.pause();
            audioPlayer.removeAttribute('src');
            try { audioPlayer.load(); } catch (_) {}

            const audioSrc = meeting.audio_path || '';
            const fallbackUrl = meeting?.id ? `/api/meetings/${meeting.id}/audio?ts=${Date.now()}` : null;
            const origin = window.location.origin;
            const isExternalAudio = !!(audioSrc && origin && !audioSrc.startsWith(origin));

            if (!audioSrc && !fallbackUrl) {
                audioSection.classList.add('hidden');
            } else {
                audioSection.classList.remove('hidden');
                let triedFallback = false;
                const finalizePlayer = () => {
                    // Mostrar el reproductor cuando haya metadata
                };
                audioPlayer.addEventListener('error', () => {
                    if (!triedFallback && fallbackUrl && audioPlayer.src !== fallbackUrl) {
                        triedFallback = true;
                        audioPlayer.src = fallbackUrl;
                        try { audioPlayer.load(); } catch (_) {}
                    }
                }, { once: true });

                if (isExternalAudio && fallbackUrl) {
                    // Ir directo al endpoint de streaming cuando el origen es externo
                    audioPlayer.src = fallbackUrl;
                    try { audioPlayer.load(); } catch (_) {}
                } else if (audioSrc) {
                    // Probar la URL directa primero
                    audioPlayer.src = audioSrc;
                    try { audioPlayer.load(); } catch (_) {}
                } else if (fallbackUrl) {
                    audioPlayer.src = fallbackUrl;
                    try { audioPlayer.load(); } catch (_) {}
                }
            }

            // Mostrar resumen
            const summaryEl = document.getElementById('meeting-summary');
            summaryEl.textContent = meeting.summary || 'No hay resumen disponible';

            // Mostrar puntos claves
            const keypointsEl = document.getElementById('meeting-keypoints');
            keypointsEl.innerHTML = '';
            if (Array.isArray(meeting.key_points) && meeting.key_points.length > 0) {
                meeting.key_points.forEach(point => {
                    const li = document.createElement('li');
                    li.className = 'flex items-start';

                    const bullet = document.createElement('span');
                    bullet.className = 'text-yellow-400 mt-1 mr-2';
                    bullet.textContent = '•';

                    const text = document.createElement('span');
                    text.textContent = point;

                    li.appendChild(bullet);
                    li.appendChild(text);
                    keypointsEl.appendChild(li);
                });
            } else {
                const emptyItem = document.createElement('li');
                emptyItem.className = 'text-slate-500';
                emptyItem.textContent = 'No hay puntos claves disponibles';
                keypointsEl.appendChild(emptyItem);
            }

            // Mostrar transcripción
            const transcriptionEl = document.getElementById('meeting-transcription');
            transcriptionEl.innerHTML = '';
            const segments = Array.isArray(meeting.segments) ? meeting.segments : [];
            if (segments.length > 0) {
                segments.forEach(segment => {
                    const containerDiv = document.createElement('div');
                    containerDiv.className = 'bg-slate-700/30 rounded p-3 border-l-2 border-yellow-400';

                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between mb-2';

                    const speakerSpan = document.createElement('span');
                    speakerSpan.className = 'text-yellow-400 font-medium';
                    speakerSpan.textContent = segment.speaker || 'Desconocido';

                    const timeSpan = document.createElement('span');
                    timeSpan.className = 'text-xs text-slate-500';
                    timeSpan.textContent = segment.time || segment.timestamp || '';

                    header.appendChild(speakerSpan);
                    header.appendChild(timeSpan);

                    const textParagraph = document.createElement('p');
                    textParagraph.className = 'text-slate-300';
                    textParagraph.textContent = segment.text || '';

                    containerDiv.appendChild(header);
                    containerDiv.appendChild(textParagraph);
                    transcriptionEl.appendChild(containerDiv);
                });
            } else {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'text-slate-500 p-3';
                emptyDiv.textContent = 'No hay transcripción disponible';
                transcriptionEl.appendChild(emptyDiv);
            }

            // Mostrar tareas
            const tasksSection = document.getElementById('meeting-tasks-section');
            const tasksEl = document.getElementById('meeting-tasks');
            tasksEl.innerHTML = '';
            const tasks = Array.isArray(meeting.tasks) ? meeting.tasks : [];
            if (tasks.length > 0) {
                tasks.forEach(task => {
                    const taskDiv = document.createElement('div');
                    taskDiv.className = 'bg-slate-700/30 rounded p-3 flex items-start';

                    const icon = document.createElement('svg');
                    icon.className = 'w-4 h-4 text-yellow-400 mt-1 mr-3 flex-shrink-0';
                    icon.setAttribute('fill', 'none');
                    icon.setAttribute('stroke', 'currentColor');
                    icon.setAttribute('viewBox', '0 0 24 24');
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />';

                    const content = document.createElement('div');
                    content.className = 'flex-1';

                    const description = document.createElement('p');
                    description.className = 'text-slate-300';
                    const taskDescription = task.description || task.descripcion || task.title || task.tarea || (typeof task === 'string' ? task : '');
                    description.textContent = taskDescription;

                    content.appendChild(description);

                    const assignee = task.assignee || task.asignado;
                    if (assignee) {
                        const assigneeText = document.createElement('p');
                        assigneeText.className = 'text-xs text-slate-500 mt-1';
                        assigneeText.textContent = `Asignado a: ${assignee}`;
                        content.appendChild(assigneeText);
                    }

                    taskDiv.appendChild(icon);
                    taskDiv.appendChild(content);
                    tasksEl.appendChild(taskDiv);
                });
                tasksSection.classList.remove('hidden');
            } else {
                tasksSection.classList.add('hidden');
            }

            // Mostrar contenido
            contentEl.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error al cargar reunión:', error);
            loadingEl.classList.add('hidden');
            errorEl.classList.remove('hidden');
            document.getElementById('meeting-modal-error-text').textContent = error.message || 'Error desconocido';
        });
}

// Event listener para cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMeetingModal();
    }
});

// Event listener para cerrar modal haciendo click fuera
document.getElementById('meeting-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMeetingModal();
    }
});
</script>

<!-- Modal de Vista Previa de PDF a pantalla completa -->
<div id="fullPreviewModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[10000] hidden">
    <div class="relative w-[95vw] h-[90vh] bg-slate-900 border border-slate-700 rounded-lg shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="absolute top-0 left-0 right-0 flex items-center justify-between bg-slate-900/90 border-b border-slate-700 px-4 py-2 z-10">
            <span class="text-slate-200 text-sm">Vista previa del PDF</span>
            <button id="closeFullPreview" class="text-slate-300 hover:text-white transition-colors" title="Cerrar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <!-- Iframe -->
        <iframe id="fullPreviewFrame" class="w-full h-full mt-[40px] bg-slate-900" title="Vista previa del PDF"></iframe>
    </div>
</div>

</body>
</html>


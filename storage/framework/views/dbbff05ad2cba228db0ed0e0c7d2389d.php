<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="user-id" content="<?php echo e(auth()->id()); ?>">
    <title>Organizaciones - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/organization.css',
        'resources/js/organization.js',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ]); ?>
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        <?php echo $__env->make('partials.navbar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('partials.mobile-nav', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="organizationPage(<?php echo \Illuminate\Support\Js::from($organizations)->toHtml() ?>)">
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
                    <?php if(!in_array($user->roles, ['free', 'basic'])): ?>
                        <button @click="openOrgModal" class="w-full bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Crear organización</button>
                        <div class="flex items-center w-full">
                            <hr class="flex-grow border-slate-700/50">
                            <span class="px-2 text-slate-400">o</span>
                            <hr class="flex-grow border-slate-700/50">
                        </div>
                    <?php endif; ?>
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
                                <div class="flex items-center justify-between mb-6">
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
                                                                                                            <div class="flex flex-col items-end text-right">
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
                            </div>

                            <!-- Pestaña Grupos -->
                            <div x-show="mainTab === 'groups'" x-transition>
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-2xl font-bold text-slate-200">Grupos de la Organización</h3>
                                    <?php if(!in_array($user->roles, ['free','basic'])): ?>
                                    <button x-show="org.user_role !== 'invitado'" @click="openGroupModal(org)" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                        Crear Grupo
                                    </button>
                                    <?php endif; ?>
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
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Invitar miembro</h2>
                        <p class="text-sm text-slate-400 mb-4" x-text="'Invitar a: ' + (selectedGroup?.nombre_grupo || '')"></p>

                        <input type="email"
                               x-model="inviteEmail"
                               @input="checkUserExists()"
                               placeholder="Correo electrónico"
                               class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 mb-3 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">

                        <!-- Mensaje de estado del usuario -->
                        <div x-show="userExistsMessage" class="mb-3 p-2 rounded-lg text-sm font-medium"
                             :class="userExists ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'">
                            <div class="flex items-center">
                                <!-- Icono para usuario existente -->
                                <template x-if="userExists">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </template>
                                <!-- Icono para usuario no existente -->
                                <template x-if="!userExists">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </template>
                                <span x-text="userExistsMessage"></span>
                            </div>
                        </div>

                        <!-- Selector de rol -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-300 mb-2">Rol en el grupo</label>
                            <select x-model="inviteRole" class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-yellow-400/50">
                                <option value="invitado">Invitado</option>
                                <option value="colaborador">Colaborador</option>
                                <option value="administrador">Administrador</option>
                            </select>
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
                <div x-show="showContainerMeetingsModal" id="container-meetings-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-40" x-cloak>
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
                            <!-- Lista de reuniones -->
                            <template x-if="selectedContainer?.meetings?.length > 0">
                                <div class="meetings-grid">
                                    <template x-for="meeting in selectedContainer.meetings" :key="meeting.id">
                                        <div>
                                            <div x-html="createMeetingCard(meeting)"></div>
                                            <p x-show="!meeting.has_transcript" class="text-xs text-slate-400 mt-2">Transcripción no disponible</p>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Estado vacío -->
                            <template x-if="!selectedContainer?.meetings?.length">
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
                                    <div class="bg-slate-800/50 border border-slate-700/50 rounded-lg p-4 hover:bg-slate-700/50 transition-colors">
                                        <h4 class="font-semibold text-yellow-400 mb-2">
                                            <span x-text="container.name"></span>
                                            <span class="company-badge ml-2" x-show="container.group_name" x-text="container.group_name"></span>
                                        </h4>
                                        <p class="text-sm text-slate-300 mb-3" x-text="container.description"></p>
                                        <div class="flex justify-between items-center text-xs text-slate-400 mb-3">
                                            <span x-text="'Reuniones: ' + (container.meetings_count || 0)"></span>
                                            <span x-text="new Date(container.created_at).toLocaleDateString()"></span>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button @click="viewContainerMeetings(container)" class="flex-1 px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition-colors">
                                                Ver Reuniones
                                            </button>
                                            <button @click="editContainer(container)" class="px-3 py-1 bg-yellow-600 text-white rounded text-xs hover:bg-yellow-700 transition-colors">
                                                Editar
                                            </button>
                                            <button x-show="canManageContainers()" @click="deleteContainer(container)" class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition-colors">
                                                Eliminar
                                            </button>
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

            </div>
        </main>
    </div>

<?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'download-meeting','maxWidth' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'download-meeting','maxWidth' => 'md']); ?>
    <div class="p-6 space-y-4 download-modal">
        <h2 class="text-xl font-semibold">Descargar reunión</h2>

        <div class="space-y-2">
            <label class="modal-option">
                <input type="checkbox" value="summary" class="download-option">
                <span>Resumen</span>
            </label>
            <label class="modal-option">
                <input type="checkbox" value="key_points" class="download-option">
                <span>Puntos Claves</span>
            </label>
            <label class="modal-option">
                <input type="checkbox" value="transcription" class="download-option">
                <span>Transcripción</span>
            </label>
            <label class="modal-option">
                <input type="checkbox" value="tasks" class="download-option">
                <span>Tareas</span>
            </label>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <button class="btn-cancel" x-on:click="$dispatch('close-modal','download-meeting')">Cancelar</button>
            <button class="confirm-download btn-primary">Descargar</button>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>

</body>
</html>

<?php /**PATH C:\laragon\www\Juntify\resources\views/organization/index.blade.php ENDPATH**/ ?>
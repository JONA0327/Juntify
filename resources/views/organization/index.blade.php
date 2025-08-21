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
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data='organizationPage(@json($organizations))'>
                <!-- Sección de crear/unirse - solo visible cuando no hay organizaciones -->
                <div x-show="organizations.length === 0" class="mb-6 flex flex-col items-center w-full max-w-sm mx-auto space-y-4">
                    <h1 class="text-2xl font-semibold text-center">Organización</h1>
                    <button @click="openOrgModal" class="w-full bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Crear organización</button>
                    <div class="flex items-center w-full">
                        <hr class="flex-grow border-slate-700/50">
                        <span class="px-2 text-slate-400">o</span>
                        <hr class="flex-grow border-slate-700/50">
                    </div>
                    <input type="text" x-model="inviteCode" placeholder="Código de invitación" class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                    <button @click="joinOrganization()" class="w-full bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-4 py-2 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Unirse</button>
                </div>

                <!-- Título cuando hay organizaciones -->
                <div x-show="organizations.length > 0" class="mb-8 text-center">
                    <h1 class="text-3xl font-semibold">Mi Organización</h1>
                </div>

                <template x-for="org in organizations" :key="org.id">
                    <div class="organization-card mb-8 text-slate-200 bg-slate-800/50 border border-slate-700/50 rounded-xl p-8 shadow-lg">
                        <div class="flex items-center mb-6">
                            <div class="organization-avatar">
                                <img :src="org.imagen || 'https://via.placeholder.com/100'" alt="" class="org-avatar">
                            </div>
                            <div class="flex-1 ml-6">
                                <h3 class="text-3xl font-bold mb-2" x-text="org.nombre_organizacion"></h3>
                                <p class="text-slate-300 text-lg mb-3" x-text="org.descripcion"></p>
                                <p class="text-lg">Miembros: <span class="font-semibold text-yellow-400" x-text="org.num_miembros"></span></p>
                            </div>
                            <button @click="openGroupModal(org)" class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 px-6 py-3 rounded-xl font-semibold text-lg shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Crear grupo</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6" x-show="org.groups && org.groups.length">
                            <template x-for="group in org.groups" :key="group.id">
                                <div @click="viewGroup(group)" class="cursor-pointer group-card p-6 text-slate-200 bg-slate-700/50 border border-slate-600/50 rounded-lg hover:bg-slate-700/70 transition-colors duration-200">
                                    <h4 class="font-semibold text-lg mb-2" x-text="group.nombre_grupo"></h4>
                                    <p class="text-sm text-slate-400 mb-3" x-text="group.descripcion"></p>
                                    <p class="text-sm text-slate-400">Miembros: <span class="text-yellow-400 font-medium" x-text="group.miembros"></span></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Modal crear organización -->
                <div x-show="showOrgModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-md text-slate-200">
                        <h2 class="text-lg font-semibold mb-4">Crear organización</h2>
                        <input type="text" x-model="newOrg.nombre_organizacion" placeholder="Nombre" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">
                        <textarea x-model="newOrg.descripcion" placeholder="Descripción (opcional)" class="w-full mb-3 p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50"></textarea>
                        <input type="file" @change="previewImage" class="mb-3 text-slate-200">
                        <template x-if="preview">
                            <img :src="preview" class="w-24 h-24 object-cover rounded-lg mb-3">
                        </template>
                        <div class="flex justify-end space-x-2">
                            <button @click="showOrgModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cancelar</button>
                            <button @click="createOrganization" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Aceptar</button>
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
                            <button @click="createGroup" class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Aceptar</button>
                        </div>
                    </div>
                </div>

                <!-- Modal información del grupo -->
                <div x-show="showGroupInfoModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
                    <div class="organization-modal p-6 w-full max-w-lg text-slate-200">
                        <h2 class="text-lg font-semibold mb-2" x-text="currentGroup?.nombre_grupo"></h2>
                        <p class="text-sm text-slate-400 mb-4" x-text="currentGroup?.descripcion"></p>

                        <table class="w-full mb-4 border border-slate-700/50">
                            <thead>
                                <tr class="bg-slate-800/50"><th class="text-left p-2">Miembro</th></tr>
                            </thead>
                            <tbody>
                                <template x-for="user in currentGroup?.users" :key="user.id">
                                    <tr class="border-t border-slate-700/50">
                                        <td class="p-2" x-text="user.username || user.email"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <div class="mb-4">
                            <button @click="showInviteOptions = !showInviteOptions" class="px-3 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">Invitar miembro</button>
                            <div x-show="showInviteOptions" class="mt-2" x-cloak>
                                <input type="email"
                                       x-model="inviteEmail"
                                       @input="checkUserExists()"
                                       placeholder="Correo electrónico"
                                       class="w-full p-2 bg-slate-900/50 border border-slate-700/50 rounded-lg placeholder-slate-500 mb-2 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50">

                                <!-- Mensaje de estado del usuario -->
                                <div x-show="userExistsMessage" class="mb-3 p-2 rounded-lg text-sm font-medium"
                                     :class="userExists ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'">
                                    <div class="flex items-center">
                                        <!-- Icono para usuario existente -->
                                        <template x-if="userExists">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5-5-5h5v-12"></path>
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

                                <!-- Botón único para enviar invitación -->
                                <button @click="sendInvitation()"
                                        :disabled="!inviteEmail"
                                        :class="{'opacity-50 cursor-not-allowed': !inviteEmail}"
                                        class="w-full px-4 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200">
                                    <span x-text="userExists ? 'Enviar Notificación Interna' : 'Enviar Invitación por Email'"></span>
                                </button>
                            </div>
                        </div>

                        <template x-if="currentGroup && !currentGroup.users.some(u => u.id === userId)">
                            <button @click="acceptInvitation" class="px-3 py-2 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 rounded-lg font-medium shadow-lg shadow-black/10 hover:from-yellow-500 hover:to-yellow-400 transition-colors duration-200 mb-4">Aceptar invitación</button>
                        </template>

                        <div class="flex justify-end">
                            <button @click="showGroupInfoModal=false" class="px-4 py-2 bg-slate-800/50 text-slate-200 rounded-lg border border-slate-700/50 hover:bg-slate-700/50 transition-colors duration-200">Cerrar</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>

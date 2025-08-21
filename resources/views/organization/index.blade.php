<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Organizaciones - Juntify</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    @include('partials.navbar')
    @include('partials.mobile-nav')

    <div class="max-w-4xl mx-auto px-4 py-8" x-data="organizationPage">
        <div class="mb-6 flex space-x-4">
            <button @click="openOrgModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Crear organización</button>
            <button class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">Introducir código de invitación</button>
        </div>

        <template x-for="org in organizations" :key="org.id">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                <div class="flex items-center">
                    <img :src="org.imagen || 'https://via.placeholder.com/64'" alt="" class="w-16 h-16 object-cover rounded mr-4">
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold" x-text="org.nombre_organizacion"></h3>
                        <p class="text-gray-600 text-sm" x-text="org.descripcion"></p>
                        <p class="text-sm mt-2">Miembros: <span x-text="org.num_miembros"></span></p>
                    </div>
                    <button @click="openGroupModal(org)" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded">Crear grupo</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4" x-show="org.groups && org.groups.length">
                    <template x-for="group in org.groups" :key="group.id">
                        <div class="border rounded p-4 bg-gray-50 dark:bg-gray-700">
                            <h4 class="font-medium" x-text="group.nombre_grupo"></h4>
                            <p class="text-sm text-gray-600" x-text="group.descripcion"></p>
                            <p class="text-xs mt-1">Miembros: <span x-text="group.miembros"></span></p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Modal crear organización -->
        <div x-show="showOrgModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow w-full max-w-md">
                <h2 class="text-lg font-semibold mb-4">Crear organización</h2>
                <input type="text" x-model="newOrg.nombre_organizacion" placeholder="Nombre" class="w-full mb-3 p-2 border rounded">
                <textarea x-model="newOrg.descripcion" placeholder="Descripción (opcional)" class="w-full mb-3 p-2 border rounded"></textarea>
                <input type="file" @change="previewImage" class="mb-3">
                <template x-if="preview">
                    <img :src="preview" class="w-24 h-24 object-cover rounded mb-3">
                </template>
                <div class="flex justify-end space-x-2">
                    <button @click="showOrgModal=false" class="px-4 py-2 bg-gray-300 rounded">Cancelar</button>
                    <button @click="createOrganization" class="px-4 py-2 bg-blue-600 text-white rounded">Aceptar</button>
                </div>
            </div>
        </div>

        <!-- Modal crear grupo -->
        <div x-show="showGroupModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-cloak>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow w-full max-w-md">
                <h2 class="text-lg font-semibold mb-4">Crear grupo</h2>
                <input type="text" x-model="newGroup.nombre_grupo" placeholder="Nombre del grupo" class="w-full mb-3 p-2 border rounded">
                <textarea x-model="newGroup.descripcion" placeholder="Descripción" class="w-full mb-3 p-2 border rounded"></textarea>
                <div class="flex justify-end space-x-2">
                    <button @click="showGroupModal=false" class="px-4 py-2 bg-gray-300 rounded">Cancelar</button>
                    <button @click="createGroup" class="px-4 py-2 bg-blue-600 text-white rounded">Aceptar</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

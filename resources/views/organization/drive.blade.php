@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6" x-data="orgDrivePage({ orgId: {{ $organization->id }} })" x-init="init()">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Drive de la organización</h1>
            <p class="text-gray-600">{{ $organization->nombre_organizacion }}</p>
        </div>
        <a href="{{ route('organization.index') }}" class="text-sm text-blue-600 hover:underline">Volver</a>
    </div>

    <div class="mb-6 flex items-center gap-3">
        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
              :class="connected ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
            <template x-if="connected">Conectado</template>
            <template x-if="!connected">No conectado</template>
        </span>

        <template x-if="!connected">
            <a :href="connectUrl" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Conectar Google Drive</a>
        </template>
        <template x-if="connected">
            <form method="POST" action="{{ route('drive.disconnect') }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm">Desconectar</button>
            </form>
        </template>
    </div>

    <div class="space-y-6">
        <div class="border rounded p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-medium">Carpeta principal</h2>
                    <p class="text-sm text-gray-600" x-show="rootFolder">
                        <span class="font-mono" x-text="rootFolder?.google_id"></span>
                    </p>
                </div>
                <template x-if="connected && !rootFolder">
                    <button @click="createRoot()" class="px-3 py-1.5 bg-gray-900 text-white rounded text-sm">Crear carpeta de organización</button>
                </template>
            </div>
        </div>

        <div class="border rounded p-4" x-show="rootFolder">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-medium">Subcarpetas</h2>
                <div class="flex items-center gap-2">
                    <input type="text" x-model="newSubfolder" placeholder="Nombre de la subcarpeta" class="border rounded px-2 py-1 text-sm" />
                    <button @click="createSubfolder()" class="px-3 py-1.5 bg-gray-900 text-white rounded text-sm">Crear</button>
                </div>
            </div>
            <template x-if="subfolders.length === 0">
                <p class="text-sm text-gray-600">No hay subcarpetas.</p>
            </template>
            <ul class="divide-y">
                <template x-for="sf in subfolders" :key="sf.id">
                    <li class="py-2 flex items-center justify-between">
                        <span class="font-medium" x-text="sf.name"></span>
                        <span class="text-xs text-gray-500 font-mono" x-text="sf.google_id"></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

@vite('resources/js/organization/drive.js')
@endsection

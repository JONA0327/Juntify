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
            <h2 class="font-medium mb-2">Estructura de almacenamiento</h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                Las subcarpetas ahora se crean y usan automáticamente. El sistema gestionará las carpetas
                <strong>Audios</strong>, <strong>Transcripciones</strong>, <strong>Audios Pospuestos</strong> y <strong>Documentos</strong> dentro de la carpeta principal.
                No es necesario crear ni administrar subcarpetas manualmente.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function orgDrivePage({ orgId }) {
    return {
        orgId,
        connected: false,
        rootFolder: null,
        subfolders: [],
        newSubfolder: '',
    connectUrl: '/auth/google/redirect?from=organization&return=' + encodeURIComponent(window.location.pathname),
        async init() {
            await this.refresh();
        },
        async refresh() {
            try {
                const res = await fetch(`/api/organizations/${this.orgId}/drive/status`, { credentials: 'include' });
                const data = await res.json();
                this.connected = !!data.connected;
                this.rootFolder = data.root_folder || null;
                this.subfolders = data.subfolders || [];
            } catch (e) { console.error(e); }
        },
        async createRoot() {
            try {
                const res = await fetch(`/api/organizations/${this.orgId}/drive/root-folder`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    credentials: 'include'
                });
                if (res.ok) { await this.refresh(); }
            } catch (e) { console.error(e); }
        },
        async createSubfolder() {
            if (!this.newSubfolder.trim()) return;
            try {
                const res = await fetch(`/api/organizations/${this.orgId}/drive/subfolders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ name: this.newSubfolder }),
                    credentials: 'include'
                });
                if (res.ok) {
                    this.newSubfolder = '';
                    await this.refresh();
                }
            } catch (e) { console.error(e); }
        }
    };
}
</script>
@endpush

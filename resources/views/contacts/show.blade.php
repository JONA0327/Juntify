@extends('layouts.app')

@section('head')
    <title>Contactos - Juntify</title>
    @vite([
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/js/reuniones_v2.js',
    ])
    <script>
        window.contactsFeatures = Object.assign(window.contactsFeatures || {}, { showChat: false });
    </script>
@endsection

@section('content')
    @include('partials.global-vars')

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pb-16 space-y-10">
        <div class="pt-6 space-y-3">
            <h1 class="text-3xl font-semibold text-white">Contactos</h1>
            <p class="text-slate-400 text-lg">Gestiona tus contactos, solicitudes y usuarios de tu organización.</p>
        </div>

        <div class="bg-slate-900/30 backdrop-blur-sm border border-slate-800/60 rounded-2xl p-6 lg:p-8 shadow-2xl shadow-black/40">
            @include('contacts.index')
        </div>
    </div>
@endsection

@section('scripts')
    @parent
    <script>
        function waitForLoadContacts() {
            if (typeof loadContacts === 'function') {
                loadContacts();
            } else {
                setTimeout(waitForLoadContacts, 100);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            waitForLoadContacts();
        });
    </script>
@endsection

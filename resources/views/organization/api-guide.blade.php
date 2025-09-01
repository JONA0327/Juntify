<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guía de API - Juntify</title>

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
        'resources/js/organization.js',
        'resources/css/audio-processing.css',
        'resources/js/reuniones_v2.js'
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">
    <div class="flex">
        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-semibold mb-6">Guía de API de Organizaciones</h1>
                <p class="mb-4">Todas las solicitudes deben incluir el encabezado <code>Authorization: Bearer &lt;token&gt;</code>.</p>

                <h2 class="text-2xl font-semibold mb-2">Endpoints básicos</h2>
                <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm mb-6">
GET {{ route('api.organizations.index') }}
POST {{ route('api.organizations.store') }}
PATCH {{ url('/api/organizations/{organization}') }}
GET {{ url('/api/groups/{group}') }}
                </pre>

                <p class="mb-4">Ejemplo utilizando <code>curl</code>:</p>
                <pre class="bg-slate-800/50 p-4 rounded-lg overflow-auto text-sm">
curl -H "Authorization: Bearer {token}" {{ route('api.organizations.index') }}

curl -X POST -H "Content-Type: application/json" \
     -H "Authorization: Bearer {token}" \
     -d '{ "nombre_organizacion": "Mi Org", "descripcion": "Descripción" }' \
     {{ route('api.organizations.store') }}
                </pre>
            </div>
        </main>
    </div>
</body>
</html>


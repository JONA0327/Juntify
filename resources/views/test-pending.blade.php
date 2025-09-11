@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-950 text-slate-200 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white mb-4">🧪 Test - Sistema de Reuniones Pendientes</h1>
                <p class="text-slate-400">Prueba funcional del sistema completo implementado</p>
            </div>

            <!-- Grid de Tests -->
            <div class="grid gap-6 md:grid-cols-2">

                <!-- Test 1: Verificar Datos de BD -->
                <div class="bg-slate-800 rounded-lg p-6 border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-3">📊 Datos de Base de Datos</h3>
                    <p class="text-slate-400 mb-4">Verificar datos actuales en las tablas</p>
                    <button onclick="checkDatabaseData()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                        Verificar BD
                    </button>
                    <div id="db-status" class="mt-4 text-sm"></div>
                </div>

                <!-- Test 2: API de Reuniones Pendientes -->
                <div class="bg-slate-800 rounded-lg p-6 border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-3">🔌 API Reuniones Pendientes</h3>
                    <p class="text-slate-400 mb-4">Probar endpoint de reuniones pendientes</p>
                    <button onclick="testPendingAPI()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors">
                        Probar API
                    </button>
                    <div id="api-status" class="mt-4 text-sm"></div>
                </div>

                <!-- Test 3: Botón Dinámico -->
                <div class="bg-slate-800 rounded-lg p-6 border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-3">🔄 Botón Dinámico</h3>
                    <p class="text-slate-400 mb-4">Verificar estado del botón según datos</p>
                    <button onclick="testDynamicButton()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition-colors">
                        Test Botón
                    </button>
                    <div id="button-status" class="mt-4 text-sm"></div>
                </div>

                <!-- Test 4: Modal Funcional -->
                <div class="bg-slate-800 rounded-lg p-6 border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-3">🪟 Modal de Reuniones</h3>
                    <p class="text-slate-400 mb-4">Probar apertura del modal</p>
                    <button onclick="testModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md transition-colors">
                        Abrir Modal
                    </button>
                    <div id="modal-status" class="mt-4 text-sm"></div>
                </div>
            </div>

            <!-- Usuario Actual -->
            <div class="bg-slate-800 rounded-lg p-6 border border-slate-700 mt-6">
                <h3 class="text-lg font-semibold text-white mb-3">👤 Usuario Actual</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-slate-400">Usuario:</span>
                        <span class="text-white ml-2">{{ Auth::user()->username ?? 'No autenticado' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Email:</span>
                        <span class="text-white ml-2">{{ Auth::user()->email ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Token Google:</span>
                        <span class="text-white ml-2">{{ Auth::user()->google_token ? '✅ Sí' : '❌ No' }}</span>
                    </div>
                </div>
            </div>

            <!-- Log de Actividades -->
            <div class="bg-slate-800 rounded-lg p-6 border border-slate-700 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-white">📝 Log de Actividades</h3>
                    <button onclick="clearLog()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                        Limpiar
                    </button>
                </div>
                <div id="test-log" class="bg-slate-900 rounded p-4 h-64 overflow-y-auto font-mono text-sm">
                    <div class="text-blue-400">[{{ now()->format('H:i:s') }}] Sistema de test iniciado</div>
                    <div class="text-green-400">[{{ now()->format('H:i:s') }}] Listo para ejecutar pruebas...</div>
                </div>
            </div>

        </div>
    </div>
</div>
@vite('resources/js/tests/test-pending.js')
@endsection

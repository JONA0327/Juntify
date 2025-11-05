@extends('layouts.app')

@section('head')
<style>
    /* Page-level styles for BNI view */
    .bni-page .download-btn {
        transition: all 0.18s ease;
    }
    .bni-page .download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.14);
    }
    .bni-page .file-size {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    /* Compactar la navbar SOLO en esta página para evitar el look vertical amplio */
    .bni-page .header {
        width: 72px !important;
        min-width: 72px !important;
    }
    .bni-page .nav-links {
        width: 72px !important;
    }
    /* Mostrar solo iconos y ocultar labels del sidebar en esta página */
    .bni-page .nav-links li a {
        padding: .75rem .5rem !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .bni-page .nav-links li a span { display: none !important; }
    .bni-page .logo { font-size: 0.85rem !important; }

    /* Ajustar el main offset para que el contenido quede centrado */
    @media (min-width: 1024px) {
        .bni-page main { padding-left: 1rem; }
    }

    /* Mejoras en las tarjetas */
    .bni-page .meeting-card {
        min-height: 220px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .bni-page .meeting-card .card-body { flex: 1 1 auto; }
    .bni-page .meeting-card .card-actions { margin-top: .75rem }

    /* Revertir compact sidebar en pantallas pequeñas (mobile) */
    @media (max-width: 1023px) {
        .bni-page .header, .bni-page .nav-links { width: auto !important; }
        .bni-page .nav-links li a span { display: inline !important; }
    }
</style>
@endsection

@section('content')
<div class="bni-page">
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <header class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6 pb-12 fade-in">
        <div class="flex-1 space-y-6">
            <div class="space-y-3">
                <h1 class="text-3xl sm:text-4xl font-bold text-white tracking-tight">Reuniones BNI</h1>
                <p class="text-slate-400 text-lg">Descarga tus archivos de audio y transcripciones .ju</p>
            </div>

            <div class="bg-gradient-to-r from-blue-500/20 to-purple-600/20 border border-blue-500/30 rounded-lg p-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-semibold text-blue-200">Cuenta BNI Activa</h3>
                        <p class="text-xs text-blue-300">Almacenamiento permanente • Sin límites • Archivos sin encriptar</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="space-y-6">
        @if($transcriptions->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($transcriptions as $transcription)
                    <div class="meeting-card bg-gradient-to-br from-slate-800/50 to-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-xl p-6 shadow-lg shadow-black/8 hover:shadow-black/16 transition-all duration-200">
                        <div class="card-body space-y-4">
                            <!-- Header -->
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold text-white truncate" title="{{ $transcription->meeting_name }}">
                                    {{ $transcription->meeting_name }}
                                </h3>
                                <div class="flex items-center gap-2 text-sm text-slate-400">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $transcription->created_at->format('d/m/Y H:i') }}
                                </div>
                                @if($transcription->description)
                                    <p class="text-sm text-slate-300 line-clamp-2">{{ $transcription->description }}</p>
                                @endif
                            </div>

                            <!-- Status -->
                            <div class="flex items-center gap-2">
                                @if($transcription->transcription_path && Storage::disk('local')->exists($transcription->transcription_path))
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-500/20 text-green-300 text-xs font-medium rounded-full">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Procesado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-500/20 text-yellow-300 text-xs font-medium rounded-full">
                                        <svg class="h-3 w-3 animate-spin" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                        </svg>
                                        Procesando
                                    </span>
                                @endif

                                @if($transcription->expires_at)
                                    <span class="text-xs text-slate-400">
                                        Expira: {{ $transcription->expires_at->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-500/20 text-blue-300 text-xs font-medium rounded-full">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732L14.146 12.8l-1.179 4.456a1 1 0 01-1.934 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732L9.854 7.2l1.179-4.456A1 1 0 0112 2z" clip-rule="evenodd"/>
                                        </svg>
                                        Permanente
                                    </span>
                                @endif
                            </div>

                            <!-- Download Buttons -->
                            <div class="card-actions space-y-3 pt-4 border-t border-slate-700/50">
                                <!-- Audio Download -->
                                @if($transcription->audio_path && Storage::disk('local')->exists($transcription->audio_path))
                                    <a href="{{ route('api.transcriptions-temp.audio', $transcription->id) }}"
                                       class="download-btn w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 shadow-lg">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/>
                                        </svg>
                                        Descargar Audio
                                        @php
                                            try {
                                                $audioSize = Storage::disk('local')->size($transcription->audio_path);
                                                $audioSizeMB = round($audioSize / 1048576, 1);
                                            } catch (\Exception $e) {
                                                $audioSizeMB = '?';
                                            }
                                        @endphp
                                        <span class="file-size">({{ $audioSizeMB }} MB)</span>
                                    </a>
                                @else
                                        <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-slate-700/50 text-slate-400 font-medium rounded-lg cursor-not-allowed">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/>
                                        </svg>
                                        Audio no disponible
                                    </div>
                                @endif

                                <!-- .ju File Download -->
                                @if($transcription->transcription_path && Storage::disk('local')->exists($transcription->transcription_path))
                                    <a href="{{ route('api.transcriptions-temp.download-ju', $transcription->id) }}"
                                       class="download-btn w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 shadow-lg">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                                        </svg>
                                        Descargar Archivo .ju
                                        @php
                                            try {
                                                $juSize = Storage::disk('local')->size($transcription->transcription_path);
                                                $juSizeKB = round($juSize / 1024, 1);
                                            } catch (\Exception $e) {
                                                $juSizeKB = '?';
                                            }
                                        @endphp
                                        <span class="file-size">({{ $juSizeKB }} KB)</span>
                                    </a>
                                @else
                                        <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-slate-700/50 text-slate-400 font-medium rounded-lg cursor-not-allowed">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                                        </svg>
                                        Archivo .ju no disponible
                                    </div>
                                @endif
                            </div>
                        </div>
                        </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($transcriptions->hasPages())
                <div class="flex justify-center mt-8">
                    {{ $transcriptions->links() }}
                </div>
            @endif

        @else
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="mx-auto max-w-md">
                    <div class="mx-auto h-24 w-24 rounded-full bg-slate-800/50 flex items-center justify-center mb-6">
                        <svg class="h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">No hay reuniones BNI</h3>
                    <p class="text-slate-400 mb-6">Aún no has subido ninguna reunión con tu cuenta BNI.</p>
                    <a href="/reuniones" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-200 shadow-lg">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                        </svg>
                        Ir a Reuniones
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div id="chat-app" class="space-y-6" data-chat-id="{{ $chat->id }}" data-current-user-id="{{ auth()->id() }}">
    <!-- Header del chat -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('contacts.index') }}"
               class="flex items-center gap-2 px-3 py-2 text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver a contactos
            </a>
            <div class="w-px h-6 bg-slate-700"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
                    {{ substr($otherUser->full_name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-200">{{ $otherUser->full_name }}</h1>
                    <p class="text-slate-400 text-sm">{{ $otherUser->email }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor del chat -->
    <div class="bg-slate-800/30 backdrop-blur-sm border border-slate-700/50 rounded-lg overflow-hidden flex flex-col" style="height: 70vh;">
        <!-- Área de mensajes -->
        <div id="messages-container" class="flex-1 p-4 overflow-y-auto space-y-3">
            <div id="messages-list">
                <div class="text-center py-8">
                    <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                    <p class="text-slate-400">Cargando mensajes...</p>
                </div>
            </div>
        </div>

        <!-- Área de entrada de mensaje -->
        <div class="border-t border-slate-700/50 p-4 bg-slate-800/50">
            <form id="message-form" class="flex items-center gap-3">
                <div class="flex-1 relative">
                    <input type="text"
                           id="message-input"
                           placeholder="Escribe tu mensaje..."
                           class="w-full px-4 py-3 bg-slate-700/50 border border-slate-600/50 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 transition-all pr-12">
                    <button type="button"
                            id="emoji-btn"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-200 transition-colors">
                        😊
                    </button>
                </div>

                <input type="file"
                       id="file-input"
                       accept="image/*,video/*,.pdf,.doc,.docx"
                       class="hidden">
                <button type="button"
                        id="file-btn"
                        class="p-3 text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                </button>

                <button type="button"
                        id="voice-btn"
                        class="p-3 text-slate-400 hover:text-slate-200 hover:bg-slate-700/50 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                    </svg>
                </button>

                <button type="submit"
                        id="send-btn"
                        class="px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

@vite('resources/css/contacts/chat.css')
@vite('resources/js/contacts/chat.js')
@endsection

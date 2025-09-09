@extends('layouts.app')

@section('content')
<div x-data="chatApp" x-init="init()" class="p-4 flex">
    <div class="w-1/3 border-r pr-4">
        <ul>
            <template x-for="chat in chats" :key="chat.id">
                <li class="mb-2">
                    <button @click="loadChat(chat.id)" class="text-blue-500">Chat <span x-text="chat.id"></span></button>
                </li>
            </template>
        </ul>
    </div>
    <div class="flex-1 pl-4">
        <div class="h-64 overflow-y-auto border mb-2 p-2">
            <template x-for="message in messages" :key="message.id">
                <div class="mb-1">
                    <strong x-text="message.sender?.name ?? message.sender_id"></strong>:
                    <span x-text="message.body"></span>
                </div>
            </template>
        </div>
        <div class="flex items-center space-x-2">
            <input x-model="newMessage" placeholder="Mensaje" class="flex-1 border rounded px-2 py-1" />
            <input type="file" x-ref="file" @change="handleFile" />
            <button type="button" @click="recordVoice" class="px-2 py-1 bg-gray-200 rounded">🎤</button>
            <button type="button" @click="send" class="px-4 py-1 bg-blue-500 text-white rounded">Enviar</button>
        </div>
    </div>
</div>
@vite('resources/js/contacts/chat.js')
@endsection

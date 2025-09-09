import axios from 'axios';
import Alpine from 'alpinejs';

Alpine.data('chatApp', () => ({
    chats: [],
    messages: [],
    activeChatId: null,
    newMessage: '',
    file: null,
    voice: null,

    async init() {
        const { data } = await axios.get('/api/chats');
        this.chats = data;
    },

    async loadChat(chatId) {
        this.activeChatId = chatId;
        const { data } = await axios.get(`/api/chats/${chatId}`);
        this.messages = data;
    },

    handleFile(e) {
        this.file = e.target.files[0];
    },

    async send() {
        if (!this.activeChatId) return;
        const form = new FormData();
        if (this.newMessage) form.append('body', this.newMessage);
        if (this.file) form.append('file', this.file);
        if (this.voice) form.append('voice', this.voice);

        const { data } = await axios.post(`/api/chats/${this.activeChatId}/messages`, form, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        this.messages.push(data);
        this.newMessage = '';
        this.file = null;
        this.voice = null;
        this.$refs.file.value = '';
    },

    async recordVoice() {
        if (!navigator.mediaDevices) return;
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const recorder = new MediaRecorder(stream);
        const chunks = [];
        recorder.ondataavailable = e => chunks.push(e.data);
        recorder.onstop = () => {
            const blob = new Blob(chunks, { type: 'audio/webm' });
            this.voice = new File([blob], 'voice.webm');
        };
        recorder.start();
        setTimeout(() => recorder.stop(), 3000); // 3s
    }
}));

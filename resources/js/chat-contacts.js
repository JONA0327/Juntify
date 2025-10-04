// Chat contactos: carga contactos y muestra en sidebar de chat
import './bootstrap';

async function fetchJSON(url, opts = {}) {
    const res = await fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin',
        ...opts
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

function avatarLetter(name) { return (name||'?').trim().charAt(0).toUpperCase() || '?'; }

async function loadChatContacts() {
    const list = document.getElementById('contacts-list');
    if (!list) return;
    list.innerHTML = '<div class="p-4 text-center text-slate-500 text-xs">Cargando...</div>';
    try {
        const data = await fetchJSON('/api/contacts');
        const contacts = data.contacts || [];
        if (!contacts.length) {
            list.innerHTML = '<div class="p-4 text-center text-slate-600 text-xs">Sin contactos</div>';
            return;
        }
        list.innerHTML = '';
        for (const c of contacts) {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between px-3 py-2 hover:bg-slate-700/40 transition cursor-pointer group';
            div.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 text-slate-900 flex items-center justify-center text-sm font-semibold">${avatarLetter(c.name)}</div>
                    <div class="leading-tight">
                        <p class="text-slate-200 text-sm font-medium">${c.name}</p>
                        <p class="text-slate-500 text-[11px]">${c.email}</p>
                    </div>
                </div>
                <button data-start-chat="${c.id}" title="Iniciar chat" class="opacity-0 group-hover:opacity-100 text-yellow-400 hover:text-yellow-300 transition p-1 rounded hover:bg-yellow-400/10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16h6m2 5l-4-4H9l-4 4V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
                </button>`;
            div.addEventListener('click', () => {
                startChatWithUser(c.id, c.name, c.email);
            });
            list.appendChild(div);
        }
    } catch (e) {
        console.error('Error loading chat contacts', e);
        list.innerHTML = '<div class="p-4 text-center text-red-400 text-xs">Error cargando contactos</div>';
    }
}

function startChatWithUser(id, name, email) {
    // Disparar evento global que el módulo principal de chat pueda captar
    const ev = new CustomEvent('chat:start-with-user', { detail: { id, name, email }});
    window.dispatchEvent(ev);
}

// Refresh button
const refreshBtn = document.getElementById('refresh-contacts');
if (refreshBtn) {
    refreshBtn.addEventListener('click', loadChatContacts);
}

// Carga inicial
window.addEventListener('DOMContentLoaded', loadChatContacts);

export { loadChatContacts };

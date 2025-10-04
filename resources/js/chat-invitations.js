import './bootstrap';

let inviteSelectedUser = null;
let inviteSearchTimeout = null;

function csrf() {
  return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}

async function api(url, opts={}) {
  const res = await fetch(url, {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
    },
    credentials: 'same-origin',
    ...opts
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function setBadge(count) {
  const badge = document.getElementById('invites-badge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count;
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
}

export async function loadInvitations() {
  const receivedList = document.getElementById('received-requests-list');
  const sentList = document.getElementById('sent-requests-list');
  if (!receivedList || !sentList) return;
  receivedList.innerHTML = '<div class="text-slate-500 text-[11px] py-2 text-center">Cargando...</div>';
  sentList.innerHTML = '<div class="text-slate-500 text-[11px] py-2 text-center">Cargando...</div>';
  try {
    const data = await api('/api/contacts/requests');
    renderReceived(data.received || []);
    renderSent(data.sent || []);
    setBadge((data.received || []).length);
  } catch (e) {
    console.error('Error invitaciones', e);
    receivedList.innerHTML = '<div class="text-red-400 text-[11px] py-2 text-center">Error</div>';
    sentList.innerHTML = '<div class="text-red-400 text-[11px] py-2 text-center">Error</div>';
  }
}

function renderReceived(received) {
  const target = document.getElementById('received-requests-list');
  if (!target) return;
  target.innerHTML = '';
  if (!received.length) {
    target.innerHTML = '<div class="text-slate-600 text-[11px] py-2 text-center">Sin pendientes</div>';
    return;
  }
  for (const r of received) {
    const el = document.createElement('div');
    el.className = 'flex items-center justify-between rounded bg-slate-700/40 px-2 py-1 hover:bg-slate-700/60 transition';
    el.innerHTML = `
      <div class="flex items-center gap-2">
        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-400 to-blue-500 flex items-center justify-center text-[10px] font-semibold text-white">${(r.sender.name||'?').charAt(0).toUpperCase()}</div>
        <div class="leading-tight">
          <p class="text-[11px] text-slate-200 font-medium">${r.sender.name}</p>
          <p class="text-[10px] text-slate-500">${r.sender.email}</p>
        </div>
      </div>
      <div class="flex items-center gap-1">
        <button data-accept="${r.id}" class="px-2 py-0.5 text-[10px] bg-green-500/80 hover:bg-green-500 text-white rounded">✔</button>
        <button data-reject="${r.id}" class="px-2 py-0.5 text-[10px] bg-red-500/80 hover:bg-red-500 text-white rounded">✖</button>
      </div>`;
    target.appendChild(el);
  }
}

function renderSent(sent) {
  const target = document.getElementById('sent-requests-list');
  if (!target) return;
  target.innerHTML = '';
  if (!sent.length) {
    target.innerHTML = '<div class="text-slate-600 text-[11px] py-2 text-center">Ninguna</div>';
    return;
  }
  for (const s of sent) {
    const el = document.createElement('div');
    el.className = 'flex items-center justify-between rounded bg-slate-700/40 px-2 py-1';
    el.innerHTML = `
      <div class="flex items-center gap-2">
        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-green-400 to-green-500 flex items-center justify-center text-[10px] font-semibold text-white">${(s.receiver.name||'?').charAt(0).toUpperCase()}</div>
        <div class="leading-tight">
          <p class="text-[11px] text-slate-200 font-medium">${s.receiver.name}</p>
          <p class="text-[10px] text-slate-500">${s.receiver.email}</p>
        </div>
      </div>
      <span class="text-[10px] text-yellow-300 bg-yellow-500/20 px-2 py-0.5 rounded">Pendiente</span>`;
    target.appendChild(el);
  }
}

async function respond(id, action) {
  try {
    await api(`/api/contacts/requests/${id}/respond`, { method: 'POST', body: JSON.stringify({ action }) });
    await loadInvitations();
    if (action === 'accept') {
      // Recargar contactos para incluir nuevo
      const ev = new Event('chat:refresh-contacts');
      window.dispatchEvent(ev);
    }
  } catch (e) { console.error('Error respond', e); }
}

function wireInvitationActions() {
  const wrap = document.getElementById('invitations-wrapper');
  if (!wrap) return;
  wrap.addEventListener('click', (e) => {
    const acc = e.target.closest('[data-accept]');
    const rej = e.target.closest('[data-reject]');
    if (acc) { respond(acc.getAttribute('data-accept'), 'accept'); }
    if (rej) { respond(rej.getAttribute('data-reject'), 'reject'); }
  });
}

function wireInviteSearch() {
  const input = document.getElementById('invite-search');
  const results = document.getElementById('invite-search-results');
  const spinner = document.getElementById('invite-search-spinner');
  const btn = document.getElementById('send-invite-btn');
  if (!input) return;
  input.addEventListener('input', () => {
    const q = input.value.trim();
    inviteSelectedUser = null;
    btn.disabled = true;
    results.classList.add('hidden');
    if (inviteSearchTimeout) clearTimeout(inviteSearchTimeout);
    if (q.length < 3) return;
    inviteSearchTimeout = setTimeout(async () => {
      spinner.classList.remove('hidden');
      try {
        const data = await api('/api/users/search', { method: 'POST', body: JSON.stringify({ query: q }) });
        renderInviteSearchResults(data.users || []);
      } catch (e) {
        console.error('search error', e);
        renderInviteSearchResults([]);
      } finally {
        spinner.classList.add('hidden');
      }
    }, 320);
  });
  btn.addEventListener('click', sendInvite);
}

function renderInviteSearchResults(users) {
  const results = document.getElementById('invite-search-results');
  const btn = document.getElementById('send-invite-btn');
  results.innerHTML = '';
  if (!users.length) {
    results.classList.add('hidden');
    return;
  }
  for (const u of users) {
    const row = document.createElement('div');
    row.className = 'px-2 py-1 rounded cursor-pointer hover:bg-slate-700/60 flex items-center justify-between text-[11px]';
    row.innerHTML = `
      <div class="flex items-center gap-2">
        <div class="w-5 h-5 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 text-slate-900 flex items-center justify-center text-[10px] font-semibold">${(u.name||'?').charAt(0).toUpperCase()}</div>
        <span class="text-slate-200">${u.name}</span>
      </div>
      <span class="text-slate-500">${u.email}</span>`;
    row.addEventListener('click', () => {
      inviteSelectedUser = u;
      btn.disabled = false;
      results.querySelectorAll('div').forEach(d => d.classList.remove('bg-slate-700/60'));
      row.classList.add('bg-slate-700/60');
    });
    results.appendChild(row);
  }
  results.classList.remove('hidden');
}

async function sendInvite() {
  const btn = document.getElementById('send-invite-btn');
  if (!inviteSelectedUser) return;
  btn.disabled = true;
  const email = inviteSelectedUser.email;
  try {
    await api('/api/contacts', { method: 'POST', body: JSON.stringify({ email }) });
    // Reset
    document.getElementById('invite-search').value = '';
    document.getElementById('invite-search-results').classList.add('hidden');
    inviteSelectedUser = null;
    await loadInvitations();
  } catch (e) {
    console.error('invite send error', e);
  } finally {
    btn.disabled = false;
  }
}

// Public/Global init
window.addEventListener('DOMContentLoaded', () => {
  loadInvitations();
  wireInvitationActions();
  wireInviteSearch();
});

// Refresh buttons
const refreshInvBtn = document.getElementById('refresh-invitations');
if (refreshInvBtn) refreshInvBtn.addEventListener('click', loadInvitations);

// External trigger to refresh contacts after accepting
window.addEventListener('chat:refresh-contacts', () => {
  // Otra parte del sistema (chat-contacts.js) escucha DOMContentLoaded; podemos forzar recarga manual si expone función global.
  if (window.loadChatContacts) window.loadChatContacts();
});

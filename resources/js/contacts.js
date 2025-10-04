import './bootstrap';

// Utilidades
const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const jsonHeaders = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() };
async function api(url, opts={}) {
  const res = await fetch(url, { credentials:'same-origin', headers: jsonHeaders, ...opts });
  if(!res.ok) throw new Error('HTTP '+res.status);
  return res.json();
}

// Estado
let searchUsers = [];
let selectedUser = null;
let debounceTimer = null;

// Elementos
const contactsList = () => document.getElementById('contacts-list');
const contactsGrid = () => document.getElementById('contacts-grid');
const contactsLoading = () => document.getElementById('contacts-loading');
const contactsCount = () => document.getElementById('contacts-count');
const receivedList = () => document.getElementById('received-requests-list');
const sentList = () => document.getElementById('sent-requests-list');
const modal = () => document.getElementById('add-contact-modal');
const searchInput = () => document.getElementById('user-search-input');
const searchResultsWrap = () => document.getElementById('search-results');
const searchResultsList = () => document.getElementById('search-results-list');
const submitBtn = () => document.getElementById('submit-btn');

// Notificaciones simples reutilizables
function notify(msg, type='info') {
  console.log(`[contacts] ${type}: ${msg}`);
}

// Cargar contactos
async function loadContacts() {
  // Soporta dos variantes: lista vertical antigua o grid nueva
  const containerList = contactsList();
  const grid = contactsGrid();
  const loading = contactsLoading();
  if(!containerList && !grid) return; // no hay UI
  if(grid && loading) {
    loading.classList.remove('hidden');
  } else if(containerList) {
    containerList.innerHTML = '<div class="loading-state flex flex-col items-center justify-center py-8"><div class="loading-spinner w-6 h-6 border-2 border-yellow-500 border-t-transparent rounded-full animate-spin mb-3"></div><p class="text-slate-400 text-center">Cargando contactos...</p></div>';
  }
  try {
    const data = await api('/api/contacts');
    const contacts = data.contacts || [];
    if(contactsCount()) contactsCount().textContent = `${contacts.length} contacto${contacts.length!==1?'s':''}`;
    if(grid) {
      // limpiar grid
      if(loading) loading.classList.add('hidden');
      grid.querySelectorAll('.contact-card').forEach(el=>el.remove());
      if(!contacts.length) {
        const empty = document.createElement('div');
        empty.className = 'col-span-full text-center text-slate-500 text-sm';
        empty.textContent = 'No tienes contactos aún';
        grid.appendChild(empty);
      } else {
        contacts.forEach(c=>{
          const card = document.createElement('div');
          card.className = 'contact-card bg-slate-900/50 border border-slate-700/50 rounded-lg p-4 flex flex-col gap-3 hover:bg-slate-900/70 transition';
          card.innerHTML = `
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center text-slate-900 font-semibold text-lg">${(c.name||'?').charAt(0).toUpperCase()}</div>
              <div class="leading-tight">
                <p class="text-slate-200 font-medium">${c.name}</p>
                <p class="text-xs text-slate-500">${c.email}</p>
              </div>
            </div>
            <div class="flex gap-2">
              <button data-open-chat="${c.id}" class="flex-1 px-3 py-1.5 text-xs bg-blue-500/20 text-blue-300 rounded hover:bg-blue-500/30 transition">Chat</button>
              <button data-remove-contact="${c.contact_record_id || ''}" data-contact-name="${(c.name||'').replace(/"/g,'&quot;')}" class="px-3 py-1.5 text-xs bg-red-500/20 text-red-300 rounded hover:bg-red-500/30 transition">Eliminar</button>
            </div>`;
          card.querySelector('[data-open-chat]')?.addEventListener('click', (e)=>{
            e.stopPropagation();
            const ev = new CustomEvent('chat:open-from-contacts', { detail: { contactId: c.id, contact: c }});
            window.dispatchEvent(ev);
          });
          grid.appendChild(card);
        });
      }
    } else if(containerList) {
      containerList.innerHTML='';
      if(!contacts.length) {
        containerList.innerHTML = '<p class="text-slate-500 text-sm">No tienes contactos aún</p>';
      } else {
        contacts.forEach(c => {
          const div = document.createElement('div');
          div.className = 'contact-card flex items-center justify-between';
          div.innerHTML = `<div class="flex items-center gap-3"><div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center text-slate-900 font-semibold">${(c.name||'?').charAt(0).toUpperCase()}</div><div><p class="font-medium text-slate-200">${c.name}</p><p class="text-xs text-slate-500">${c.email}</p></div></div><button data-open-chat="${c.id}" class="px-3 py-1 text-xs bg-blue-500/20 text-blue-300 rounded hover:bg-blue-500/30 transition">Chat</button>`;
          div.querySelector('[data-open-chat]')?.addEventListener('click', (e)=>{
            e.stopPropagation();
            const ev = new CustomEvent('chat:open-from-contacts', { detail: { contactId: c.id, contact: c }});
            window.dispatchEvent(ev);
          });
          containerList.appendChild(div);
        });
      }
    }
  } catch(e){
    if(grid) {
      if(loading) loading.classList.add('hidden');
      const err = document.createElement('div');
      err.className = 'col-span-full text-center text-red-400 text-sm';
      err.textContent = 'Error cargando contactos';
      grid.appendChild(err);
    } else if(containerList) {
      containerList.innerHTML = '<p class="text-red-400 text-sm">Error cargando contactos</p>';
    }
  }
}

// Cargar solicitudes
async function loadRequests() {
  if(!receivedList() || !sentList()) return;
  receivedList().innerHTML = '<p class="text-slate-400">Cargando...</p>';
  sentList().innerHTML = '<p class="text-slate-400">Cargando...</p>';
  try {
    const data = await api('/api/contacts/requests');
    renderReceived(data.received||[]);
    renderSent(data.sent||[]);
  } catch(e){
    receivedList().innerHTML = '<p class="text-red-400 text-sm">Error</p>';
    sentList().innerHTML = '<p class="text-red-400 text-sm">Error</p>';
  }
}

function renderReceived(list) {
  receivedList().innerHTML='';
  if(!list.length) { receivedList().innerHTML='<p class="text-slate-500 text-sm">Sin solicitudes</p>'; return; }
  list.forEach(r=>{
    const row = document.createElement('div');
    row.className = 'request-card flex items-center justify-between';
    row.innerHTML = `<div class="flex items-center gap-3"><div class="w-8 h-8 rounded-full bg-blue-500/30 flex items-center justify-center text-blue-300 font-semibold">${(r.sender.name||'?').charAt(0).toUpperCase()}</div><div><p class="text-sm text-slate-200 font-medium">${r.sender.name}</p><p class="text-xs text-slate-500">${r.sender.email}</p></div></div><div class="flex gap-2"><button data-accept="${r.id}" class="px-3 py-1 rounded bg-green-500/70 hover:bg-green-500 text-white text-xs">Aceptar</button><button data-reject="${r.id}" class="px-3 py-1 rounded bg-red-500/70 hover:bg-red-500 text-white text-xs">Rechazar</button></div>`;
    receivedList().appendChild(row);
  });
}

function renderSent(list){
  sentList().innerHTML='';
  if(!list.length){ sentList().innerHTML='<p class="text-slate-500 text-sm">Sin enviadas</p>'; return; }
  list.forEach(s=>{
    const row = document.createElement('div');
    row.className = 'request-card flex items-center justify-between';
    row.innerHTML = `<div class="flex items-center gap-3"><div class="w-8 h-8 rounded-full bg-green-500/30 flex items-center justify-center text-green-300 font-semibold">${(s.receiver.name||'?').charAt(0).toUpperCase()}</div><div><p class="text-sm text-slate-200 font-medium">${s.receiver.name}</p><p class="text-xs text-slate-500">${s.receiver.email}</p></div></div><div class="flex items-center gap-2"><span class="text-[11px] text-yellow-300 bg-yellow-500/20 px-2 py-0.5 rounded">Pendiente</span><button data-cancel-request="${s.id}" class="text-[10px] px-2 py-0.5 rounded bg-slate-600 hover:bg-slate-500 text-slate-200">Cancelar</button></div>`;
    sentList().appendChild(row);
  });
}

// Responder solicitud
async function respondRequest(id, action){
  try { await api(`/api/contacts/requests/${id}/respond`, { method:'POST', body: JSON.stringify({ action })});
    await loadRequests();
    if(action==='accept') await loadContacts();
  } catch(e){ notify('Error procesando solicitud','error'); }
}


// Búsqueda en modal
function wireSearchModal(){
  if(!searchInput()) return;
  searchInput().addEventListener('input', ()=>{
    const q = searchInput().value.trim();
    selectedUser = null;
    submitBtn().disabled = true;
    searchResultsList().innerHTML='';
    searchResultsWrap().classList.add('hidden');
    if(debounceTimer) clearTimeout(debounceTimer);
    if(q.length < 3) return;
    debounceTimer = setTimeout(async ()=>{
      try {
        const data = await api('/api/users/search', { method:'POST', body: JSON.stringify({ query: q })});
        searchUsers = data.users||[];
        renderSearchResults();
      } catch(e){ searchUsers=[]; renderSearchResults(); }
    }, 320);
  });
}

function renderSearchResults(){
  if(!searchResultsWrap()) return;
  searchResultsList().innerHTML='';
  if(!searchUsers.length){ searchResultsWrap().classList.add('hidden'); return; }
  searchUsers.forEach(u=>{
    const item=document.createElement('div');
    item.className='user-search-item px-3 py-2 rounded-md hover:bg-slate-700/40 transition';
    item.innerHTML=`<div class="flex items-center gap-3"><div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center text-slate-900 font-semibold">${(u.name||'?').charAt(0).toUpperCase()}</div><div><p class="text-sm text-slate-200 font-medium">${u.name}</p><p class="text-xs text-slate-500">${u.email}</p></div></div>`;
    item.addEventListener('click',()=>{
      selectedUser=u;
      submitBtn().disabled=false;
      document.querySelectorAll('#search-results-list .user-search-item').forEach(el=>{
        el.classList.remove('bg-slate-700/60','border','border-yellow-400/50','shadow-sm');
      });
      item.classList.add('bg-slate-700/60','border','border-yellow-400/50','shadow-sm');
    });
    searchResultsList().appendChild(item);
  });
  searchResultsWrap().classList.remove('hidden');
}

// Enviar invitación
async function sendInvitation(email){
  try { await api('/api/contacts', { method:'POST', body: JSON.stringify({ email })}); notify('Solicitud enviada','success'); await loadRequests(); }
  catch(e){ notify('Error enviando solicitud','error'); }
}

// Eliminar contacto
async function deleteContact(contactRecordId){
  if(!contactRecordId) return;
  try {
    const resp = await fetch(`/api/contacts/${contactRecordId}`, { method:'DELETE', headers: { 'Accept':'application/json','X-CSRF-TOKEN': csrf() }});
    if(!resp.ok) throw new Error();
    notify('Contacto eliminado','success');
    await loadContacts();
  } catch(e){ notify('Error eliminando','error'); }
}

// Cancelar solicitud enviada
async function cancelSentRequest(notificationId){
  if(!notificationId) return;
  try {
    const resp = await fetch(`/api/contacts/requests/${notificationId}`, { method:'DELETE', headers: { 'Accept':'application/json','X-CSRF-TOKEN': csrf() }});
    if(!resp.ok) throw new Error();
    notify('Solicitud cancelada','success');
    await loadRequests();
  } catch(e){ notify('Error cancelando','error'); }
}

// Eventos globales
function wireGlobalEvents(){
  document.addEventListener('click',(e)=>{
    const acc = e.target.closest('[data-accept]');
    const rej = e.target.closest('[data-reject]');
    const inv = e.target.closest('[data-invite-org]');
    const del = e.target.closest('[data-remove-contact]');
    const cancelReq = e.target.closest('[data-cancel-request]');
    if(acc) respondRequest(acc.getAttribute('data-accept'),'accept');
    if(rej) respondRequest(rej.getAttribute('data-reject'),'reject');
    if(inv) sendInvitation(inv.getAttribute('data-invite-org'));
    if(del) {
      const id = del.getAttribute('data-remove-contact');
      const name = del.getAttribute('data-contact-name') || 'este contacto';
      showContactDeleteModal(id, name);
    }
    if(cancelReq) cancelSentRequest(cancelReq.getAttribute('data-cancel-request'));
  });
  const form = document.getElementById('add-contact-form');
  if(form){ form.addEventListener('submit', async (e)=>{ e.preventDefault(); if(!selectedUser) return; await sendInvitation(selectedUser.email); closeModal(); }); }
  const addBtn = document.getElementById('add-contact-btn');
  const cancelBtn = document.getElementById('cancel-btn');
  const closeModalBtn = document.getElementById('close-modal-btn');
  if(addBtn) addBtn.addEventListener('click', openModal);
  if(cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
}

function openModal(){ if(modal()) { modal().classList.remove('hidden'); modal().classList.add('flex'); } }
function closeModal(){ if(modal()) { modal().classList.add('hidden'); modal().classList.remove('flex'); } }

// Búsqueda en lista de contactos
function wireInlineContactsSearch(){
  const input = document.getElementById('contact-search');
  if(!input) return;
  input.addEventListener('input', ()=>{
    const term = input.value.toLowerCase();
    contactsList().querySelectorAll('.contact-card').forEach(card=>{
      const text = card.textContent.toLowerCase();
      card.style.display = text.includes(term)?'':'none';
    });
  });
}

// Chat inline (modal pequeño reutilizando markup existente del index parcial) - placeholder
function openInlineChat(contact){
  // Si existe un modal de chat en index parcial, rellenarlo (ya está en index.blade incluido)
  const chatModal = document.getElementById('chat-modal');
  if(!chatModal) { notify('Chat no disponible en esta vista aún','info'); return; }
  document.getElementById('chat-contact-name').textContent = contact.name;
  document.getElementById('chat-contact-email').textContent = contact.email;
  document.getElementById('chat-contact-avatar').textContent = (contact.name||'?').charAt(0).toUpperCase();
  chatModal.classList.remove('hidden');
  chatModal.classList.add('flex');
}

function wireChatModalClose(){
  const closeBtn = document.getElementById('close-chat-modal');
  if(closeBtn) closeBtn.addEventListener('click', ()=>{ const m=document.getElementById('chat-modal'); if(m){ m.classList.add('hidden'); m.classList.remove('flex'); }});
}

// Modal confirmación eliminar contacto
function ensureDeleteModal(){
  if(document.getElementById('confirm-delete-contact-modal')) return;
  const wrap = document.createElement('div');
  wrap.id='confirm-delete-contact-modal';
  wrap.className='hidden fixed inset-0 z-[120] items-center justify-center bg-black/60 backdrop-blur-sm';
  wrap.innerHTML=`<div class="w-full max-w-sm mx-auto bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-5 flex flex-col gap-4">
    <h3 class="text-sm font-semibold text-slate-200">Eliminar contacto</h3>
    <p id="confirm-delete-contact-text" class="text-xs text-slate-400 leading-relaxed">¿Seguro que deseas eliminar este contacto?</p>
    <div class="flex justify-end gap-2 text-xs">
      <button type="button" data-delete-cancel class="px-3 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200">Cancelar</button>
      <button type="button" data-delete-confirm class="px-3 py-1 rounded bg-red-600 hover:bg-red-500 text-white font-medium">Eliminar</button>
    </div>
  </div>`;
  document.body.appendChild(wrap);
  // eventos
  wrap.addEventListener('click', (e)=>{ if(e.target===wrap) hideDeleteModal(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') hideDeleteModal(); });
  wrap.querySelector('[data-delete-cancel]').addEventListener('click', hideDeleteModal);
  wrap.querySelector('[data-delete-confirm]').addEventListener('click', async ()=>{
    const id = wrap.getAttribute('data-contact-id');
    if(id){
      wrap.querySelector('[data-delete-confirm]').disabled = true;
      wrap.querySelector('[data-delete-confirm]').textContent='Eliminando...';
      await deleteContact(id);
    }
    hideDeleteModal();
  });
}

function showContactDeleteModal(contactId, name){
  ensureDeleteModal();
  const modal = document.getElementById('confirm-delete-contact-modal');
  modal.setAttribute('data-contact-id', contactId);
  modal.querySelector('#confirm-delete-contact-text').innerHTML = `¿Seguro que deseas eliminar <span class="text-slate-200 font-medium">${escapeHtml(name)}</span>? Esta acción no se puede deshacer.`;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function hideDeleteModal(){
  const modal = document.getElementById('confirm-delete-contact-modal');
  if(!modal) return;
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  const btn = modal.querySelector('[data-delete-confirm]');
  if(btn){ btn.disabled=false; btn.textContent='Eliminar'; }
  modal.removeAttribute('data-contact-id');
}

function escapeHtml(str){
  return (str||'').replace(/[&<>"]+/g, s=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[s]));
}

// Init on demand (para pestaña en chat)
async function initContactsModule(){
  if (window.__contactsModuleInitialized) return; // evitar doble init
  window.__contactsModuleInitialized = true;
  loadContacts();
  loadRequests();
  // Organización removida
  wireSearchModal();
  wireGlobalEvents();
  ensureDeleteModal();
  wireInlineContactsSearch();
  wireChatModalClose();
}

window.contactsModule = {
  init: initContactsModule,
  reload: ()=>{ loadContacts(); loadRequests(); }
};

import './bootstrap';

const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

async function api(url, opts={}) {
  const res = await fetch(url, { headers: { 'Accept':'application/json','X-CSRF-TOKEN': csrf() }, credentials:'same-origin', ...opts });
  if(!res.ok) throw new Error('HTTP '+res.status);
  return res.json().catch(()=>({}));
}

function timeAgo(dateStr){
  if(!dateStr) return '';
  const d = new Date(dateStr);
  const diff = (Date.now()-d.getTime())/1000;
  if(diff<60) return 'ahora';
  if(diff<3600) return Math.floor(diff/60)+'m';
  if(diff<86400) return Math.floor(diff/3600)+'h';
  return d.toLocaleDateString();
}

export async function loadConversations(){
  const list = document.getElementById('conversations-list');
  if(!list) return;
  list.innerHTML = `<div class="p-4 text-center text-slate-500 text-xs">Cargando...</div>`;
  try {
    const data = await fetch('/api/chats');
    if(!data.ok) throw new Error('status '+data.status);
    const chats = await data.json();
    if(!Array.isArray(chats) || !chats.length){
      list.innerHTML = '<div class="p-6 text-center text-slate-600 text-sm">Sin conversaciones</div>';
      return;
    }
    list.innerHTML='';
    chats.forEach(ch=>{
      const other = ch.other_user||{};
      const last = ch.last_message;
      const div = document.createElement('div');
      div.className='conversation-item group flex items-center gap-3 px-3 py-2 hover:bg-slate-700/40 transition cursor-pointer border-b border-slate-800/40';
      div.setAttribute('data-chat-id', ch.id);
      div.innerHTML=`
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center text-slate-900 font-semibold">${(other.name||'?').charAt(0).toUpperCase()}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-200 truncate">${other.name||'Desconocido'}</p>
            <span class="text-[10px] text-slate-500 ml-2">${timeAgo(ch.updated_at)}</span>
          </div>
          <p class="text-[11px] text-slate-500 truncate">${last? (last.is_mine? 'Tú: ':'')+ (last.body||'[archivo]') : 'Sin mensajes'}</p>
        </div>
  <button title="Eliminar conversación" data-delete-chat="${ch.id}" class="transition p-1 rounded text-slate-500 hover:text-red-400 hover:bg-red-500/10">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v2M4 7h16" />
          </svg>
        </button>`;
      div.addEventListener('click', (e)=>{
        if(e.target.closest('[data-delete-chat]')) return; // evitar abrir si es delete
        // Simular click para cargar chat (ya existe lógica en otro script quizá)
        window.__activeChatId = ch.id;
        if(typeof openChatById==='function') openChatById(ch.id); else console.log('Abrir chat', ch.id);
      });
      list.appendChild(div);
    });
  } catch(e){
    console.error('Error cargando conversaciones', e);
    list.innerHTML='<div class="p-4 text-center text-red-400 text-xs">Error cargando conversaciones</div>';
  }
}

async function deleteConversation(chatId){
  if(!chatId) return;
  showChatDeleteModal(chatId);
}

// --- Modal de confirmación eliminación chat ---
function ensureChatDeleteModal(){
  if(document.getElementById('confirm-delete-chat-modal')) return;
  const wrap = document.createElement('div');
  wrap.id='confirm-delete-chat-modal';
  wrap.className='hidden fixed inset-0 z-[130] items-center justify-center bg-black/60 backdrop-blur-sm';
  wrap.innerHTML=`<div class="w-full max-w-sm mx-auto bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-5 flex flex-col gap-4">
    <h3 class="text-sm font-semibold text-slate-200">Eliminar conversación</h3>
    <p class="text-xs text-slate-400 leading-relaxed">¿Seguro que deseas eliminar esta conversación? Se borrarán todos los mensajes y no podrás recuperarlos.</p>
    <div class="flex justify-end gap-2 text-xs">
      <button type="button" data-chat-delete-cancel class="px-3 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200">Cancelar</button>
      <button type="button" data-chat-delete-confirm class="px-3 py-1 rounded bg-red-600 hover:bg-red-500 text-white font-medium">Eliminar</button>
    </div>
  </div>`;
  document.body.appendChild(wrap);
  wrap.addEventListener('click',(e)=>{ if(e.target===wrap) hideChatDeleteModal(); });
  document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') hideChatDeleteModal(); });
  wrap.querySelector('[data-chat-delete-cancel]').addEventListener('click', hideChatDeleteModal);
  wrap.querySelector('[data-chat-delete-confirm]').addEventListener('click', async ()=>{
    const id = wrap.getAttribute('data-chat-id');
    if(!id) return;
    const btn = wrap.querySelector('[data-chat-delete-confirm]');
    btn.disabled=true; btn.textContent='Eliminando...';
    try {
      const res = await fetch(`/api/chats/${id}`, { method:'DELETE', headers:{ 'Accept':'application/json','X-CSRF-TOKEN': csrf() }});
      if(!res.ok) throw new Error('HTTP '+res.status);
      // Si el chat eliminado era el activo, reset UI
      if(window.__activeChatId && parseInt(window.__activeChatId,10) === parseInt(id,10)){
        const active = document.getElementById('active-chat');
        const placeholder = document.getElementById('no-chat-selected');
        if(active && placeholder){ active.classList.add('hidden'); placeholder.classList.remove('hidden'); }
        window.__activeChatId = null;
      }
      await loadConversations();
    } catch(err){ console.error('Error eliminando chat', err); }
    hideChatDeleteModal();
  });
}

function showChatDeleteModal(chatId){
  ensureChatDeleteModal();
  const modal = document.getElementById('confirm-delete-chat-modal');
  modal.setAttribute('data-chat-id', chatId);
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}
function hideChatDeleteModal(){
  const modal = document.getElementById('confirm-delete-chat-modal');
  if(!modal) return;
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  modal.removeAttribute('data-chat-id');
  const btn = modal.querySelector('[data-chat-delete-confirm]');
  if(btn){ btn.disabled=false; btn.textContent='Eliminar'; }
}

document.addEventListener('click', (e)=>{
  const del = e.target.closest('[data-delete-chat]');
  if(del){
    const id = del.getAttribute('data-delete-chat');
    deleteConversation(id);
  }
});

window.addEventListener('DOMContentLoaded', ()=>{
  loadConversations();
  ensureChatDeleteModal();
});

window.loadConversations = loadConversations;

document.addEventListener('DOMContentLoaded', () => {
  const titleEl = document.getElementById('cal-title');
  const cellsContainer = document.getElementById('cal-cells');
  const cellTpl = document.getElementById('cal-cell-template');
  const eventTpl = document.getElementById('cal-event-template');
  const btnPrev = document.getElementById('cal-prev');
  const btnNext = document.getElementById('cal-next');
  const btnToday = document.getElementById('cal-today');

  if (!titleEl || !cellsContainer || !cellTpl || !eventTpl) return; // no calendar on page

  const csrf = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '');
  const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  let viewDate = new Date();
  viewDate.setDate(1);
  let eventsByDate = {}; // key: 'YYYY-MM-DD' => [{title,status,priority,...}]
  let selectedDateStr = null;

  if (btnPrev) btnPrev.addEventListener('click', () => { viewDate.setMonth(viewDate.getMonth()-1); loadAndRender(); });
  if (btnNext) btnNext.addEventListener('click', () => { viewDate.setMonth(viewDate.getMonth()+1); loadAndRender(); });
  if (btnToday) btnToday.addEventListener('click', () => { const d = new Date(); viewDate = new Date(d.getFullYear(), d.getMonth(), 1); loadAndRender(); });

  function fmtDate(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  }
  function getMonthRange(date){
    const start = new Date(date.getFullYear(), date.getMonth(), 1);
    const end = new Date(date.getFullYear(), date.getMonth()+1, 0);
    return { start, end };
  }

  async function fetchEvents(date){
    const { start, end } = getMonthRange(date);
    const params = new URLSearchParams({ start: fmtDate(start), end: fmtDate(end) });
    const base = window.taskData?.apiTasks || '/api/tasks';
    const url = new URL(base, window.location.origin);
    url.search = params.toString();
    const res = await fetch(url);
    const data = await res.json();
    eventsByDate = {};
    for (const ev of data){
      if (!ev.start) continue;
      const day = ev.start.substring(0,10);
      if (!eventsByDate[day]) eventsByDate[day] = [];
      eventsByDate[day].push(ev);
    }
  }

  function colorClasses(ev){
    const status = ev.extendedProps?.status;
    switch(status){
      case 'completed': return 'bg-green-500/20 text-green-300 border-green-400/30';
      case 'overdue': return 'bg-red-500/20 text-red-300 border-red-400/30';
      case 'in_progress': return 'bg-blue-500/20 text-blue-300 border-blue-400/30';
      case 'pending':
      default: return 'bg-yellow-500/20 text-yellow-300 border-yellow-400/30';
    }
  }

  function renderCalendar(date){
    titleEl.textContent = monthNames[date.getMonth()] + ' ' + date.getFullYear();
    cellsContainer.innerHTML = '';

    const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
    const startWeekday = (firstDay.getDay()); // 0=Dom
    const daysInMonth = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();
    const totalCells = 42;
    const todayStr = fmtDate(new Date());
    const prevMonthDays = startWeekday;
    const prevMonthLastDate = new Date(date.getFullYear(), date.getMonth(), 0).getDate();

    for (let i=0;i<totalCells;i++){
      let cellDate; let isOther=false;
      if (i < prevMonthDays){
        const dayNum = prevMonthLastDate - prevMonthDays + 1 + i;
        cellDate = new Date(date.getFullYear(), date.getMonth()-1, dayNum);
        isOther = true;
      } else if (i >= prevMonthDays + daysInMonth){
        const dayNum = i - (prevMonthDays + daysInMonth) + 1;
        cellDate = new Date(date.getFullYear(), date.getMonth()+1, dayNum);
        isOther = true;
      } else {
        const dayNum = i - prevMonthDays + 1;
        cellDate = new Date(date.getFullYear(), date.getMonth(), dayNum);
      }

      const cell = cellTpl.content.cloneNode(true);
      const dayNumEl = cell.querySelector('.day-number');
      const todayBadge = cell.querySelector('.today-badge');
      const eventsEl = cell.querySelector('.events');
      const iso = fmtDate(cellDate);
      dayNumEl.textContent = String(cellDate.getDate());
      const wrapper = cell.firstElementChild;
      if (isOther) wrapper.classList.add('opacity-50');
      if (iso === todayStr){ todayBadge.classList.remove('hidden'); wrapper.classList.add('border','border-blue-400/40','bg-blue-500/5'); }

      const dayEvents = eventsByDate[iso] || [];
      for (const ev of dayEvents.slice(0,2)){
        const chip = eventTpl.content.cloneNode(true).firstElementChild;
        chip.className += ' ' + colorClasses(ev);
        const raw = (ev.title || 'Tarea');
        const clean = raw.replace(/\s+/g, ' ').trim();
        chip.title = clean; chip.textContent = clean; chip.classList.add('h-5','leading-5');
        eventsEl.appendChild(chip);
      }
      if (dayEvents.length > 2){
        const more = document.createElement('div'); more.className='text-[11px] text-slate-400'; more.textContent = `+${dayEvents.length-2} más`; eventsEl.appendChild(more);
      }
      wrapper.addEventListener('click', ()=>{ selectedDateStr = iso; showDayTasks(iso); });
      cellsContainer.appendChild(cell);
    }
  }

  async function loadAndRender(){ await fetchEvents(viewDate); renderCalendar(viewDate); if (!selectedDateStr) { selectedDateStr = fmtDate(new Date()); } showDayTasks(selectedDateStr); }
  loadAndRender();

  function dotColorByStatus(status){
    switch(status){
      case 'completed': return 'bg-green-400';
      case 'overdue': return 'bg-red-400';
      case 'in_progress': return 'bg-blue-400';
      case 'pending': default: return 'bg-yellow-400';
    }
  }

  function formatTimeFromEvent(ev){
    try{
      if (ev.start && ev.start.includes('T')){
        const d = new Date(ev.start); if (!isNaN(d)) return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
      }
      const h = ev.extendedProps?.hora_limite; if (h) return h;
    }catch(_){ }
    return '--:--';
  }

  function showDayTasks(iso){
    const panel = document.getElementById('day-tasks-panel');
    const title = document.getElementById('day-title');
    const list = document.getElementById('day-task-list');
    const [y,m,d] = iso.split('-');
    const jsDate = new Date(parseInt(y), parseInt(m)-1, parseInt(d));
    const nice = `${d} de ${monthNames[jsDate.getMonth()]}`;
    title.textContent = `Tareas para el ${nice}`;
    list.innerHTML = '';
    const dayEvents = eventsByDate[iso] || [];
    if (dayEvents.length === 0){
      list.innerHTML = '<div class="text-slate-400 text-sm">No hay tareas para este día.</div>';
      panel.classList.remove('hidden');
      return;
    }
    for (const ev of dayEvents){
      const pr = ev.extendedProps?.priority || '-';
      const assignee = ev.extendedProps?.asignado || ev.extendedProps?.assignee || '—';
      const prog = typeof ev.extendedProps?.progress === 'number' ? ev.extendedProps.progress + '%' : '-';
      const remain = calcRemaining(iso, ev);

      const row = document.createElement('button');
      row.className = 'w-full text-left rounded-md px-3 py-2 flex items-start gap-3 border ' + colorClasses(ev) + ' hover:opacity-80';

      const dot = document.createElement('span');
      dot.className = 'w-2.5 h-2.5 rounded-full ' + dotColorByStatus(ev.extendedProps?.status);

      const timeEl = document.createElement('div');
      timeEl.className = 'text-xs text-slate-400 w-16';
      timeEl.textContent = formatTimeFromEvent(ev);

      const info = document.createElement('div');
      info.className = 'flex-1';

      const titleEl = document.createElement('div');
      titleEl.className = 'text-sm text-slate-200 truncate';
      titleEl.textContent = ev.title || 'Tarea';

      const tags = document.createElement('div');
      tags.className = 'mt-1 flex flex-wrap gap-1';
      const prTag = document.createElement('span'); prTag.className = 'task-tag'; prTag.textContent = `Prioridad: ${pr}`;
      const asTag = document.createElement('span'); asTag.className = 'task-tag'; asTag.textContent = `Asignado: ${assignee}`;
      tags.appendChild(prTag); tags.appendChild(asTag);

      info.appendChild(titleEl);
      info.appendChild(tags);

      const meta = document.createElement('div');
      meta.className = 'text-xs text-slate-400 text-right min-w-[140px]';
      meta.innerHTML = `<div>Progreso: ${prog}</div><div>${remain}</div>`;

      row.appendChild(dot);
      row.appendChild(timeEl);
      row.appendChild(info);
      row.appendChild(meta);

      row.addEventListener('click', ()=> openTaskDetailsModal(ev.id));
      list.appendChild(row);
    }
    panel.classList.remove('hidden');
  }

  function calcRemaining(dayIso, ev){
    try{
      let due;
      if (ev.start && ev.start.includes('T')){
        due = new Date(ev.start);
      } else if (ev.extendedProps?.hora_limite){
        due = new Date(`${dayIso}T${ev.extendedProps.hora_limite}:00`);
      } else {
        // fin del día seleccionado
        due = new Date(`${dayIso}T23:59:59`);
      }
      const now = new Date();
      const diffMs = due.getTime() - now.getTime();
      const human = humanizeDuration(Math.abs(diffMs));
      if (isNaN(diffMs)) return '';
      if (diffMs >= 0) return `Faltan ${human}`;
      return `Vencida hace ${human}`;
    } catch(_){ return ''; }
  }

  function humanizeDuration(ms){
    const sec = Math.floor(ms/1000);
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
  }

  async function openTaskDetailsModal(taskId){
    try{
      const res = await fetch(new URL(`/api/tasks-laravel/tasks/${taskId}`, window.location.origin), { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success) throw new Error('No se pudo cargar la tarea');
      const t = data.task;
      const modal = document.createElement('div');
      modal.id='taskDetailsModal'; modal.className='fixed inset-0 bg-black/60 z-[3001] flex items-start justify-center p-6';
      modal.innerHTML = `
        <div class="bg-slate-900 rounded-xl border border-slate-700 w-full max-w-3xl overflow-hidden shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700">
            <div>
              <div class="text-slate-200 font-semibold">${t.tarea}</div>
              <div class="text-slate-400 text-sm">Prioridad: ${t.prioridad || '-'} • Progreso: ${t.progreso || 0}%</div>
            </div>
            <button class="text-slate-400 hover:text-slate-200" data-close>&times;</button>
          </div>
          <div class="px-6 pt-3 flex gap-3 border-b border-slate-800">
            <button class="tab-btn active px-3 py-2 text-sm rounded-t bg-slate-800 text-slate-200" data-tab="details">Detalles</button>
            <button class="tab-btn px-3 py-2 text-sm rounded-t text-slate-300 hover:text-white" data-tab="comments">Comentarios</button>
            <button class="tab-btn px-3 py-2 text-sm rounded-t text-slate-300 hover:text-white" data-tab="files">Archivos</button>
          </div>
          <div class="p-6">

<div class="tab-panel" id="tab-details">
  <div class="text-slate-300 whitespace-pre-line">${t.descripcion || 'Sin descripción'}</div>
  <div class="mt-4 text-slate-400 text-sm space-y-1">
    <div>Reunión: ${t.meeting_name || '-'}</div>
    <div>Fecha inicio: ${t.fecha_inicio || '-'}</div>
    <div>Asignado: ${t.asignado || '-'}</div>
    <div>Progreso: ${(typeof t.progreso === 'number' ? t.progreso : 0)}%</div>
    <div>Fecha límite: ${(t.fecha_limite || '-')} ${t.hora_limite ? ' ' + t.hora_limite : ''}</div>
  </div>
  <div class="mt-4 flex items-center gap-2" id="progress-controls">
    <input type="range" min="0" max="100" value="${t.progreso || 0}" id="task-progress" class="flex-1" />
    <span id="task-progress-value" class="text-slate-200 text-sm w-12 text-right">${t.progreso || 0}%</span>
    <button id="task-progress-save" class="bg-blue-600 hover:bg-blue-500 text-white text-xs px-2 py-1 rounded">Guardar</button>
    <button id="task-mark-complete" class="bg-green-600 hover:bg-green-500 text-white text-xs px-2 py-1 rounded">Completar</button>
  </div>
</div>
            <div class="tab-panel hidden" id="tab-comments">
              <div id="comments-list" class="flex flex-col gap-3"></div>
              <form id="comment-form" class="mt-3 flex gap-2">
                <input class="flex-1 bg-slate-800 border border-slate-700 rounded px-3 py-2 text-slate-200" placeholder="Escribe un comentario" />
                <button class="btn btn-primary px-4 py-2 rounded bg-blue-600 text-white">Enviar</button>
              </form>
            </div>
            <div class="tab-panel hidden" id="tab-files">
              <div class="mb-3">
                <label class="text-slate-300 text-sm">Carpeta de Drive</label>
                <select id="drive-folder" class="w-full bg-slate-800 border border-slate-700 rounded px-3 py-2 text-slate-200"></select>
              </div>
              <div class="flex items-center gap-2 mb-4">
                <input type="file" id="file-input" class="text-slate-300 text-sm" />
                <button id="upload-btn" class="bg-green-600 hover:bg-green-500 text-white text-sm px-3 py-2 rounded">Subir</button>
              </div>
              <div id="files-list" class="flex flex-col gap-2"></div>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modal); document.body.classList.add('overflow-hidden');
      modal.addEventListener('click', (e)=>{ if (e.target.dataset.close !== undefined || e.target === modal){ modal.remove(); document.body.classList.remove('overflow-hidden'); } });
        modal.querySelectorAll('.tab-btn').forEach(btn=>{
          btn.addEventListener('click', ()=>{
            modal.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active','bg-slate-800','text-slate-200'));
            btn.classList.add('active','bg-slate-800','text-slate-200');
            const tab = btn.dataset.tab;
            modal.querySelectorAll('.tab-panel').forEach(p=>p.classList.add('hidden'));
            modal.querySelector('#tab-'+tab).classList.remove('hidden');
          });
        });
        const progressInput = modal.querySelector('#task-progress');
      const progressValue = modal.querySelector('#task-progress-value');
      progressInput.addEventListener('input', ()=>{ progressValue.textContent = progressInput.value + '%'; });
      modal.querySelector('#task-progress-save').addEventListener('click', async ()=>{
        const value = parseInt(progressInput.value,10);
        try{
          const r = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}`, window.location.origin), {
            method:'PUT',
            headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ tarea: t.tarea, progreso: value }),
            credentials: 'same-origin'
          });
          const resp = await r.json();
          if(resp.success){
            t.progreso = value;
          } else {
            alert('No se pudo actualizar progreso');
          }
        }catch(_){ alert('No se pudo actualizar progreso'); }
      });
      modal.querySelector('#task-mark-complete').addEventListener('click', async ()=>{
        try{
          const r = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}/complete`, window.location.origin), {
            method:'POST',
            headers:{ 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin'
          });
          const resp = await r.json();
          if(resp.success){
            progressInput.value = 100;
            progressValue.textContent = '100%';
            t.progreso = 100;
          } else {
            alert('No se pudo completar la tarea');
          }
        }catch(_){ alert('No se pudo completar la tarea'); }
      });

      await loadComments();
      async function loadComments(){
        const res = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}/comments`, window.location.origin), { credentials: 'same-origin' });
        const data = await res.json();
        const list = modal.querySelector('#comments-list');
        list.innerHTML='';

        const renderComment = (c, container)=>{
          const wrapper = document.createElement('div');
          wrapper.className = 'mt-2';
          const box = document.createElement('div');
          box.className = 'bg-slate-800/70 border border-slate-700 rounded px-3 py-2 text-sm text-slate-200';
          const when = c.date ? new Date(c.date).toLocaleString() : '';
          box.textContent = `${c.author}: ${c.text}${when ? ' ('+when+')' : ''}`;
          const replyBtn = document.createElement('button');
          replyBtn.textContent = 'Responder';
          replyBtn.className = 'ml-2 text-xs text-sky-400';
          box.appendChild(replyBtn);
          const childrenContainer = document.createElement('div');
          childrenContainer.className = 'ml-4';
          wrapper.appendChild(box);
          wrapper.appendChild(childrenContainer);

          replyBtn.addEventListener('click',()=>{
            replyBtn.disabled = true;
            const form = document.createElement('form');
            form.className = 'mt-1';
            form.innerHTML = `<input type="text" class="w-full rounded bg-slate-900/70 border border-slate-700 px-2 py-1 text-slate-200 text-sm" placeholder="Respuesta...">`;
            const input = form.querySelector('input');
            form.onsubmit = async (e)=>{
              e.preventDefault();
              const text = input.value.trim();
              if(!text) return;
              const r = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}/comments`, window.location.origin), {
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ text, parent_id: c.id }),
                credentials: 'same-origin'
              });
              const resp = await r.json();
              if(resp.success){ await loadComments(); }
            };
            wrapper.insertBefore(form, childrenContainer);
          });

          container.appendChild(wrapper);
          (c.children||[]).forEach(ch=>renderComment(ch, childrenContainer));
        };

        (data.comments||[]).forEach(c=>renderComment(c, list));

        const form = modal.querySelector('#comment-form');
        form.onsubmit = async (e)=>{
          e.preventDefault();
          const input=form.querySelector('input');
          const text=input.value.trim();
          if(!text) return;
          const r = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}/comments`, window.location.origin), {
            method:'POST',
            headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ text }),
            credentials: 'same-origin'
          });
          const resp = await r.json();
          if (resp.success){ input.value=''; await loadComments(); }
        };
      }

      let meetingFolderId = null;
      try{
        const mRes = await fetch(`/api/meetings/${t.meeting_id}`);
        const mData = await mRes.json();
        meetingFolderId = mData.meeting?.recordings_folder_id || null;
      }catch(_){}

      await loadFolders(meetingFolderId); await loadFiles();
      async function loadFolders(rootId){ const sel = modal.querySelector('#drive-folder'); const url = rootId ? `/api/drive/folders?parents=${encodeURIComponent(rootId)}` : '/api/drive/folders'; const res = await fetch(url); const data = await res.json(); sel.innerHTML=''; (data.folders||[]).forEach(f=>{ const opt=document.createElement('option'); opt.value=f.id; opt.textContent=f.name; sel.appendChild(opt); }); }
        async function loadFiles(){ const list = modal.querySelector('#files-list'); const res = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}/files`, window.location.origin), { credentials: 'same-origin' }); const data = await res.json(); list.innerHTML=''; (data.files||[]).forEach(f=>{ const row=document.createElement('div'); row.className='flex items-center justify-between bg-slate-800/60 border border-slate-700 rounded px-3 py-2'; const nameLink=document.createElement('a'); nameLink.href=`/api/tasks-laravel/files/${f.id}/download`; nameLink.target='_blank'; nameLink.textContent=f.name; nameLink.className='text-slate-200 hover:underline flex-1'; const preview=document.createElement('a'); preview.href=f.drive_web_link; preview.target='_blank'; preview.textContent='Vista previa'; preview.className='text-xs text-blue-400 hover:underline ml-4'; row.appendChild(nameLink); row.appendChild(preview); list.appendChild(row); }); }
        modal.querySelector('#upload-btn').addEventListener('click', async ()=>{ const fileInput = modal.querySelector('#file-input'); const folder = modal.querySelector('#drive-folder').value; if(!fileInput.files.length){ alert('Selecciona un archivo'); return; } const fd = new FormData(); fd.append('folder_id', folder); fd.append('file', fileInput.files[0]); const r = await fetch(new URL(`/api/tasks-laravel/tasks/${t.id}/files`, window.location.origin), { method:'POST', headers:{ 'X-CSRF-TOKEN': csrf }, body: fd, credentials: 'same-origin' }); const resp = await r.json(); if(resp.success){ fileInput.value=''; await loadFiles(); } else { alert('No se pudo subir el archivo'); } });
    } catch(e){ console.error(e); alert('Error al abrir detalles de la tarea'); }
  }
});

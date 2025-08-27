document.addEventListener('DOMContentLoaded', () => {
  if (window.__miniCalendarInited) return; // evita inicializar dos veces
  window.__miniCalendarInited = true;
  const titleEl = document.getElementById('mini-title');
  const grid = document.getElementById('mini-grid');
  const prevBtn = document.getElementById('mini-prev');
  const nextBtn = document.getElementById('mini-next');
  const dayTitle = document.getElementById('mini-day-title');
  const list = document.getElementById('mini-task-list');
  if (!titleEl || !grid || !dayTitle || !list) return;

  const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  let viewDate = new Date(); viewDate.setDate(1);
  let eventsByDate = {}; let selectedDay = new Date();

  if (prevBtn) prevBtn.addEventListener('click', ()=> { viewDate.setMonth(viewDate.getMonth()-1); loadAndRender(); });
  if (nextBtn) nextBtn.addEventListener('click', ()=> { viewDate.setMonth(viewDate.getMonth()+1); loadAndRender(); });

  function fmtDate(d){ const y=d.getFullYear(); const m=String(d.getMonth()+1).padStart(2,'0'); const day=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${day}`; }
  function getMonthRange(date){ const start=new Date(date.getFullYear(), date.getMonth(), 1); const end=new Date(date.getFullYear(), date.getMonth()+1, 0); return {start,end}; }

  async function fetchEvents(date){
    const {start,end} = getMonthRange(date);
    const params = new URLSearchParams({ start: fmtDate(start), end: fmtDate(end) });
    const url = (window.taskData?.apiTasks || '/api/tasks') + '?' + params.toString();
    const res = await fetch(url); const data = await res.json();
    eventsByDate = {}; for (const ev of data){ if (!ev.start) continue; const day = ev.start.substring(0,10); if (!eventsByDate[day]) eventsByDate[day]=[]; eventsByDate[day].push(ev); }
  }

  function renderGrid(){
    titleEl.textContent = monthNames[viewDate.getMonth()] + ' ' + viewDate.getFullYear();
    const today = new Date(); today.setHours(0,0,0,0);
    // limpia celdas previas (deja encabezados de días)
    while (grid.children.length > 7) grid.removeChild(grid.lastChild);

    const firstDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
    const startWeekday = firstDay.getDay();
    const daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth()+1, 0).getDate();
    const prevMonthLastDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 0).getDate();
    const totalCells = 42;
    for (let i=0;i<totalCells;i++){
      let d; let other=false;
      if (i < startWeekday){ d = new Date(viewDate.getFullYear(), viewDate.getMonth()-1, prevMonthLastDate - startWeekday + 1 + i); other=true; }
      else if (i >= startWeekday + daysInMonth){ d = new Date(viewDate.getFullYear(), viewDate.getMonth()+1, i - (startWeekday + daysInMonth) + 1); other=true; }
      else { d = new Date(viewDate.getFullYear(), viewDate.getMonth(), i - startWeekday + 1); }

      const span = document.createElement('span');
      span.className = 'cursor-pointer mx-auto w-7 h-7 flex items-center justify-center rounded-full hover:bg-slate-700/40 transition-colors';
      span.textContent = d.getDate();
      if (other) span.classList.add('text-gray-600');
      const iso = fmtDate(d);
      if (fmtDate(today) === iso) span.classList.add('bg-blue-600','text-white');
      if (fmtDate(selectedDay) === iso) span.classList.add('ring-1','ring-blue-400');
      span.addEventListener('click', ()=> { selectedDay = d; renderDay(); renderGrid(); });
      grid.appendChild(span);
    }
  }

  function formatTime(ev){
    try{ if (ev.start && ev.start.includes('T')){ const d=new Date(ev.start); if(!isNaN(d)) return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }
      const h = ev.extendedProps?.hora_limite; if (h) return h;
    }catch(_){ }
    return '';
  }

  function renderDay(){
    const iso = fmtDate(selectedDay);
    const nice = `${selectedDay.getDate()} de ${monthNames[selectedDay.getMonth()]}`;
    dayTitle.textContent = `Tareas para el ${nice}`;
    list.innerHTML='';
    const dayEvents = eventsByDate[iso] || [];
    if (dayEvents.length === 0){ list.innerHTML = '<div class="text-slate-400 text-sm">No hay tareas para este día.</div>'; return; }
    for (const ev of dayEvents){
      const row = document.createElement('div'); row.className='px-2 py-1 rounded hover:bg-slate-800/70 text-left truncate border border-slate-700/40 bg-slate-800/40';
      const time = formatTime(ev);
      row.textContent = time ? `${time} — ${ev.title || 'Tarea'}` : (ev.title || 'Tarea');
      row.title = ev.title || '';
      list.appendChild(row);
    }
  }

  async function loadAndRender(){ await fetchEvents(viewDate); renderGrid(); selectedDay = new Date(); renderDay(); }
  loadAndRender();
});

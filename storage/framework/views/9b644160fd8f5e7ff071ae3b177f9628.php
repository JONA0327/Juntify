<!-- Calendario mensual dinámico -->
<div class="bg-slate-900/50 border border-slate-700/50 rounded-xl p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button id="cal-prev" class="btn btn-secondary">Anterior</button>
            <button id="cal-today" class="btn btn-secondary">Hoy</button>
            <button id="cal-next" class="btn btn-secondary">Siguiente</button>
        </div>
        <div id="cal-title" class="text-lg font-semibold text-slate-200"></div>
        <div class="hidden md:block text-slate-400 text-sm">Clic en un día para ver tareas</div>
    </div>

    <div class="rounded-lg overflow-hidden border border-slate-700/30">
        <!-- Encabezados de días -->
        <div class="grid grid-cols-7 bg-slate-800/60">
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium border-r border-slate-700/30 last:border-r-0">Dom</div>
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium border-r border-slate-700/30 last:border-r-0">Lun</div>
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium border-r border-slate-700/30 last:border-r-0">Mar</div>
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium border-r border-slate-700/30 last:border-r-0">Mié</div>
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium border-r border-slate-700/30 last:border-r-0">Jue</div>
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium border-r border-slate-700/30 last:border-r-0">Vie</div>
            <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-3 text-center font-medium">Sáb</div>
        </div>
        <!-- Grid de casillas del calendario -->
        <div id="cal-cells" class="grid grid-cols-7 bg-slate-900/30"></div>
    </div>
</div>

<template id="cal-cell-template">
    <div class="bg-slate-900/60 min-h-[120px] relative cursor-pointer hover:bg-slate-800/60 transition-colors border-r border-b border-slate-700/30 last:border-r-0 overflow-hidden flex flex-col">
        <div class="flex justify-between items-start p-2 pb-0">
            <span class="text-sm font-medium day-number leading-none"></span>
            <span class="text-[7px] px-1.5 py-0.5 rounded-full hidden today-badge bg-green-600 text-white font-bold shadow-sm">HOY</span>
        </div>
        <div class="task-bars-container flex-1 p-2 pt-1"></div>
    </div>
</template>

<template id="cal-task-bar-template">
    <div class="absolute h-6 rounded-lg text-white text-xs font-medium flex items-center px-2 cursor-pointer hover:opacity-90 transition-opacity z-10"></div>
</template>

<style>
/* Asegurar que las celdas del calendario tengan el mismo tamaño */
#cal-cells {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
}

#cal-cells > div {
    min-height: 120px;
    width: 100%;
}

/* Mejores bordes para el grid */
#cal-cells > div:nth-child(7n) {
    border-right: none;
}

/* Contenedor de barras de tareas */
.task-bars-container {
    position: relative;
    overflow: visible;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

/* Barras de tareas - diferentes colores por estado */
.task-status-completed {
    background: #10b981 !important; /* Verde */
}

.task-status-overdue {
    background: #ef4444 !important; /* Rojo */
}

.task-status-in_progress {
    background: #3b82f6 !important; /* Azul */
}

.task-status-pending {
    background: #f59e0b !important; /* Amarillo/Orange */
}

/* Efecto hover para las barras */
.task-bars-container > div:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* Asegurar que el texto se vea bien en las barras */
.task-bars-container > div {
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    height: 18px;
    line-height: 18px;
    font-size: 11px;
    padding: 0 6px;
    border-radius: 4px;
    margin-bottom: 1px;
}

/* Responsive para móviles */
@media (max-width: 768px) {
    #cal-cells > div {
        min-height: 80px;
    }

    .task-bars-container > div {
        height: 16px;
        line-height: 16px;
        font-size: 10px;
        padding: 0 4px;
    }

    .day-number {
        font-size: 12px;
    }
}

@media (max-width: 640px) {
    #cal-cells > div {
        min-height: 60px;
    }

    .task-bars-container > div {
        height: 14px;
        line-height: 14px;
        font-size: 9px;
        padding: 0 3px;
    }

    .day-number {
        font-size: 11px;
    }

    .today-badge {
        font-size: 6px;
        padding: 1px 4px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Calendario: Iniciando...');

    const titleEl = document.getElementById('cal-title');
    const cellsContainer = document.getElementById('cal-cells');
    const cellTpl = document.getElementById('cal-cell-template');
    const btnPrev = document.getElementById('cal-prev');
    const btnNext = document.getElementById('cal-next');
    const btnToday = document.getElementById('cal-today');

    console.log('📍 Elementos encontrados:', {
        titleEl: !!titleEl,
        cellsContainer: !!cellsContainer,
        cellTpl: !!cellTpl,
        btnPrev: !!btnPrev,
        btnNext: !!btnNext,
        btnToday: !!btnToday
    });

    if (!titleEl || !cellsContainer || !cellTpl) {
        console.error('❌ Elementos del calendario no encontrados!');
        return;
    }

    const csrf = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '');
    const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    let viewDate = new Date();
    viewDate.setDate(1);
    let eventsByDate = {};

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
        try {
            const { start, end } = getMonthRange(date);
            const params = new URLSearchParams({ start: fmtDate(start), end: fmtDate(end) });
            const url = '/api/tasks-laravel/calendar?' + params.toString();
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                }
            });
            const data = await res.json();
            eventsByDate = {};

            const events = Array.isArray(data) ? data : [];
            for (const ev of events){
                if (!ev.start) continue;
                const day = ev.start.substring(0,10);
                if (!eventsByDate[day]) eventsByDate[day] = [];
                eventsByDate[day].push(ev);
            }
        } catch (error) {
            console.error('Error fetching calendar events:', error);
        }
    }

    function formatTimeFromEvent(ev){
        try{
            if (ev.start && ev.start.includes('T')){
                const d = new Date(ev.start);
                if (!isNaN(d)) return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            }
            const h = ev.extendedProps?.hora_limite;
            if (h) return h.substring(0, 5);
        }catch(_){ }
        return '--:--';
    }

    function getStatusClass(ev) {
        const status = ev.extendedProps?.status;
        switch(status){
            case 'completed': return 'task-status-completed';
            case 'overdue': return 'task-status-overdue';
            case 'in_progress': return 'task-status-in_progress';
            case 'pending':
            default: return 'task-status-pending';
        }
    }

    function renderCalendar(date) {
        console.log('Renderizando calendario...', date);
        titleEl.textContent = monthNames[date.getMonth()] + ' ' + date.getFullYear();
        cellsContainer.innerHTML = '';

        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        const startWeekday = (firstDay.getDay()); // 0=Dom
        const daysInMonth = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();
        const totalCells = 42;
        const todayStr = fmtDate(new Date());
        console.log('📆 Fecha de HOY detectada:', todayStr);
        const prevMonthDays = startWeekday;
        const prevMonthLastDate = new Date(date.getFullYear(), date.getMonth(), 0).getDate();

        console.log('Creando', totalCells, 'celdas...');

        // Crear todas las celdas del calendario
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
            const iso = fmtDate(cellDate);

            dayNumEl.textContent = String(cellDate.getDate());
            const wrapper = cell.firstElementChild;

            if (isOther) {
                wrapper.classList.add('opacity-50');
                dayNumEl.classList.add('text-slate-600');
            } else {
                dayNumEl.classList.add('text-slate-300');
            }

            // Día actual en verde
            if (iso === todayStr){
                console.log('🎯 Aplicando estilo de HOY a:', iso, cellDate.getDate());
                todayBadge.classList.remove('hidden');
                wrapper.classList.add('ring-2', 'ring-green-400/50', 'bg-green-500/10');
                dayNumEl.classList.remove('text-slate-300');
                dayNumEl.classList.add('text-green-300', 'font-bold');
            } else {
                console.log('📅 Día normal:', iso, cellDate.getDate());
            }

            cellsContainer.appendChild(cell);
        }

        console.log('Celdas creadas. Renderizando barras de tareas...');
        // Por ahora, renderizar sin barras complejas
        console.log('Calendario renderizado exitosamente');
    }

    function renderTaskBars() {
        console.log('Función renderTaskBars llamada');
        // Por ahora simplificado - mostrar tareas básicas
        for (const [dateStr, events] of Object.entries(eventsByDate)) {
            console.log('Procesando fecha:', dateStr, 'con', events.length, 'eventos');
        }
    }

    async function loadAndRender(){
        console.log('🔄 Cargando y renderizando calendario...');
        await fetchEvents(viewDate);
        console.log('📅 Eventos cargados:', eventsByDate);
        renderCalendar(viewDate);
        console.log('✅ Calendario renderizado!');
    }

    console.log('🎬 Iniciando carga inicial...');
    loadAndRender();
});
</script>
<?php /**PATH C:\laragon\www\Juntify\resources\views/tasks/partials/_calendar-main.blade.php ENDPATH**/ ?>
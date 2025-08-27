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

        <div class="rounded-lg overflow-hidden">
            <div class="grid grid-cols-7 gap-px bg-slate-800">
                <!-- Nombres de los días -->
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Dom</div>
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Lun</div>
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Mar</div>
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Mié</div>
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Jue</div>
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Vie</div>
                <div class="bg-slate-800/80 text-slate-300 text-xs uppercase tracking-wide p-2 text-center">Sáb</div>
            </div>
            <div id="cal-cells" class="grid grid-cols-7 gap-px bg-slate-800"></div>
        </div>
</div>

<template id="cal-cell-template">
    <div class="bg-slate-900/60 min-h-[96px] p-2 relative rounded-md">
        <div class="flex items-center justify-between">
            <span class="text-xs text-slate-400 day-number"></span>
            <span class="text-[10px] px-1 py-[1px] rounded-full hidden today-badge bg-blue-500/20 text-blue-300 border border-blue-400/30 absolute top-1 right-1 pointer-events-none">Hoy</span>
        </div>
        <div class="mt-2 flex flex-col gap-1 events max-h-24 overflow-hidden"></div>
    </div>
</template>

<template id="cal-event-template">
    <div class="block w-full truncate text-[10px] px-1.5 py-0.5 rounded border"></div>

</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const titleEl = document.getElementById('cal-title');
    const cellsContainer = document.getElementById('cal-cells');
    const cellTpl = document.getElementById('cal-cell-template');
    const eventTpl = document.getElementById('cal-event-template');
    const btnPrev = document.getElementById('cal-prev');
    const btnNext = document.getElementById('cal-next');
    const btnToday = document.getElementById('cal-today');

    const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    let viewDate = new Date();
    viewDate.setDate(1);
    let eventsByDate = {}; // key: 'YYYY-MM-DD' => [{title,status,priority,...}]

    btnPrev.addEventListener('click', () => { viewDate.setMonth(viewDate.getMonth()-1); loadAndRender(); });
    btnNext.addEventListener('click', () => { viewDate.setMonth(viewDate.getMonth()+1); loadAndRender(); });
    btnToday.addEventListener('click', () => { const d = new Date(); viewDate = new Date(d.getFullYear(), d.getMonth(), 1); loadAndRender(); });

    function fmtDate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const day = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${day}`;
    }

    function getMonthRange(date) {
        const start = new Date(date.getFullYear(), date.getMonth(), 1);
        const end = new Date(date.getFullYear(), date.getMonth()+1, 0);
        return { start, end };
    }

    async function fetchEvents(date) {
        const { start, end } = getMonthRange(date);
        const params = new URLSearchParams({ start: fmtDate(start), end: fmtDate(end) });
        const url = (window.taskData?.apiTasks || '/api/tasks') + '?' + params.toString();
        const res = await fetch(url);
        const data = await res.json();
        // Reset map
        eventsByDate = {};
        for (const ev of data) {
            if (!ev.start) continue;
            const day = ev.start.substring(0,10);
            if (!eventsByDate[day]) eventsByDate[day] = [];
            eventsByDate[day].push(ev);
        }
    }

        function colorClasses(ev) {
            const status = ev.extendedProps?.status;
            const priority = ev.extendedProps?.priority;
            if (status === 'completed') return 'bg-green-500/20 text-green-300 border-green-400/30';
            // compute overdue if start date is before today and not completed
            try {
                const today = new Date(); today.setHours(0,0,0,0);
                if (status !== 'completed' && ev.start) {
                    const d = new Date(ev.start); d.setHours(0,0,0,0);
                    if (d < today) return 'bg-red-500/20 text-red-300 border-red-400/30';
                }
            } catch (e) {}
            if (priority === 'alta') return 'bg-yellow-500/20 text-yellow-300 border-yellow-400/30';
            if (priority === 'media') return 'bg-blue-500/20 text-blue-300 border-blue-400/30';
            if (priority === 'baja') return 'bg-slate-500/20 text-slate-300 border-slate-400/30';
            return 'bg-slate-700/30 text-slate-300 border-slate-500/30';
        }

    function renderCalendar(date) {
        titleEl.textContent = monthNames[date.getMonth()] + ' ' + date.getFullYear();
        cellsContainer.innerHTML = '';

        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        const startWeekday = (firstDay.getDay()); // 0=Dom
        const daysInMonth = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();

        const totalCells = 42; // 6 semanas x 7 días
        const todayStr = fmtDate(new Date());

        // Compute previous month tail
        const prevMonthDays = startWeekday;
        const prevMonthLastDate = new Date(date.getFullYear(), date.getMonth(), 0).getDate();

        for (let i = 0; i < totalCells; i++) {
            let cellDate;
            let isOther = false;
            if (i < prevMonthDays) {
                // prev month
                const dayNum = prevMonthLastDate - prevMonthDays + 1 + i;
                cellDate = new Date(date.getFullYear(), date.getMonth()-1, dayNum);
                isOther = true;
            } else if (i >= prevMonthDays + daysInMonth) {
                // next month
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

            // style other month cells
            const wrapper = cell.firstElementChild;
            if (isOther) {
                wrapper.classList.add('opacity-50');
            }
            // today mark
                            if (iso === todayStr) {
                                todayBadge.classList.remove('hidden');
                                wrapper.classList.remove('ring-1','ring-inset','ring-blue-500/30');
                                wrapper.classList.add('border','border-blue-400/40','bg-blue-500/5');
                            }

            // render events for the day
            const dayEvents = eventsByDate[iso] || [];
                    for (const ev of dayEvents.slice(0, 2)) { // up to 2 chips for compact layout
                const chip = eventTpl.content.cloneNode(true).firstElementChild;
                chip.className += ' ' + colorClasses(ev);
                        const raw = (ev.title || 'Tarea');
                        const clean = raw.replace(/\s+/g, ' ').trim();
                        chip.title = clean;
                        chip.textContent = clean;
                        chip.classList.add('h-5','leading-5');
                eventsEl.appendChild(chip);
            }
                    if (dayEvents.length > 2) {
                const more = document.createElement('div');
                more.className = 'text-[11px] text-slate-400';
                        more.textContent = `+${dayEvents.length - 2} más`;
                eventsEl.appendChild(more);
            }

            cellsContainer.appendChild(cell);
        }
    }

    async function loadAndRender() {
        await fetchEvents(viewDate);
        renderCalendar(viewDate);
    }

    loadAndRender();
});
</script>
<?php /**PATH C:\laragon\www\Juntify\resources\views/tasks/partials/_calendar-main.blade.php ENDPATH**/ ?>
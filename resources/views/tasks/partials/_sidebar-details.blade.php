<!-- Sidebar con detalles de Tareas -->
<div class="info-card p-6">
    <!-- Mini Calendario -->
    <div class="text-center text-white mb-8">
        <div class="flex justify-between items-center mb-4">
            <button id="mini-cal-prev" class="text-gray-400 hover:text-white">&lt;</button>
            <h4 id="mini-cal-title" class="font-semibold">Agosto 2025</h4>
            <button id="mini-cal-next" class="text-gray-400 hover:text-white">&gt;</button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-sm">
            <!-- Días -->
            <span class="text-gray-500 text-center py-1">D</span>
            <span class="text-gray-500 text-center py-1">L</span>
            <span class="text-gray-500 text-center py-1">M</span>
            <span class="text-gray-500 text-center py-1">M</span>
            <span class="text-gray-500 text-center py-1">J</span>
            <span class="text-gray-500 text-center py-1">V</span>
            <span class="text-gray-500 text-center py-1">S</span>
            <!-- Fechas dinámicas -->
            <div id="mini-cal-dates" class="contents">
                <!-- Las fechas se generarán dinámicamente aquí -->
            </div>
        </div>
    </div>

    <!-- Lista de Tareas para el día seleccionado -->
    <div id="mini-cal-tasks-section">
        <h3 id="mini-cal-tasks-title" class="card-title text-base border-t border-blue-900/50 pt-6">Tareas del día</h3>

        <div id="mini-cal-tasks-list" class="flex flex-col space-y-3">
            <!-- Las tareas se cargarán dinámicamente aquí -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const miniCalTitle = document.getElementById('mini-cal-title');
    const miniCalDates = document.getElementById('mini-cal-dates');
    const miniCalPrev = document.getElementById('mini-cal-prev');
    const miniCalNext = document.getElementById('mini-cal-next');
    const miniCalTasksTitle = document.getElementById('mini-cal-tasks-title');
    const miniCalTasksList = document.getElementById('mini-cal-tasks-list');

    if (!miniCalTitle || !miniCalDates) return;

    const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    let miniViewDate = new Date();
    miniViewDate.setDate(1);
    let miniEventsByDate = {};
    let selectedDate = null;

    const csrf = (window.taskLaravel?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '');

    if (miniCalPrev) miniCalPrev.addEventListener('click', () => {
        miniViewDate.setMonth(miniViewDate.getMonth()-1);
        loadMiniCalendar();
    });

    if (miniCalNext) miniCalNext.addEventListener('click', () => {
        miniViewDate.setMonth(miniViewDate.getMonth()+1);
        loadMiniCalendar();
    });

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

    async function fetchMiniEvents(date){
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
            miniEventsByDate = {};

            const events = Array.isArray(data) ? data : [];
            for (const ev of events){
                if (!ev.start) continue;
                const day = ev.start.substring(0,10);
                if (!miniEventsByDate[day]) miniEventsByDate[day] = [];
                miniEventsByDate[day].push(ev);
            }
        } catch (error) {
            console.error('Error fetching mini calendar events:', error);
        }
    }

    function renderMiniCalendar(date) {
        miniCalTitle.textContent = monthNames[date.getMonth()] + ' ' + date.getFullYear();
        miniCalDates.innerHTML = '';

        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        const startWeekday = (firstDay.getDay()); // 0=Dom
        const daysInMonth = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();
        const todayStr = fmtDate(new Date());
        const prevMonthDays = startWeekday;
        const prevMonthLastDate = new Date(date.getFullYear(), date.getMonth(), 0).getDate();

        // Días del mes anterior
        for (let i = prevMonthDays - 1; i >= 0; i--) {
            const dayNum = prevMonthLastDate - i;
            const span = document.createElement('span');
            span.className = 'text-gray-600 cursor-pointer text-center py-1 hover:bg-blue-600/20 rounded transition-colors';
            span.textContent = dayNum;
            miniCalDates.appendChild(span);
        }

        // Días del mes actual
        for (let day = 1; day <= daysInMonth; day++) {
            const cellDate = new Date(date.getFullYear(), date.getMonth(), day);
            const iso = fmtDate(cellDate);
            const span = document.createElement('span');
            span.className = 'cursor-pointer text-center py-1 hover:bg-blue-600/20 rounded transition-colors';
            span.textContent = day;

            // Estilo para hoy (verde)
            if (iso === todayStr) {
                span.className += ' bg-green-500 text-white rounded-full w-6 h-6 flex items-center justify-center mx-auto font-medium';
            }
            // Estilo para día seleccionado (azul)
            else if (selectedDate === iso) {
                span.className += ' bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center mx-auto font-medium';
            }
            // Días con tareas (indicador azul)
            else {
                const dayEvents = miniEventsByDate[iso] || [];
                if (dayEvents.length > 0) {
                    span.className += ' text-blue-300 font-medium relative';
                    span.title = `${dayEvents.length} tarea(s)`;

                    // Pequeño indicador
                    const indicator = document.createElement('div');
                    indicator.className = 'absolute top-0 right-0 w-1.5 h-1.5 bg-blue-400 rounded-full';
                    span.appendChild(indicator);
                    span.style.position = 'relative';
                } else {
                    span.className += ' text-slate-300';
                }
            }

            span.addEventListener('click', () => {
                selectedDate = iso;
                showMiniDayTasks(iso);
                renderMiniCalendar(date); // Re-render para mostrar selección
            });

            miniCalDates.appendChild(span);
        }

        // Días del mes siguiente
        const totalCells = 42;
        const currentCells = prevMonthDays + daysInMonth;
        for (let day = 1; currentCells + day - 1 < totalCells; day++) {
            const span = document.createElement('span');
            span.className = 'text-gray-600 cursor-pointer text-center py-1 hover:bg-blue-600/20 rounded transition-colors';
            span.textContent = day;
            miniCalDates.appendChild(span);
        }
    }

    function formatTimeFromEvent(ev){
        try{
            if (ev.start && ev.start.includes('T')){
                const d = new Date(ev.start);
                if (!isNaN(d)) return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', hour12: true});
            }
            const h = ev.extendedProps?.hora_limite;
            if (h) {
                const [hour, minute] = h.split(':');
                const date = new Date();
                date.setHours(parseInt(hour), parseInt(minute));
                return date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', hour12: true});
            }
        }catch(_){ }
        return '--:--';
    }

    function showMiniDayTasks(iso) {
        const [y,m,d] = iso.split('-');
        const jsDate = new Date(parseInt(y), parseInt(m)-1, parseInt(d));
        const nice = `${d} de ${monthNames[jsDate.getMonth()]}`;
        miniCalTasksTitle.textContent = `Tareas para el ${nice}`;
        miniCalTasksList.innerHTML = '';

        const dayEvents = miniEventsByDate[iso] || [];

        if (dayEvents.length === 0) {
            miniCalTasksList.innerHTML = '<div class="text-slate-400 text-sm text-center py-4">No hay tareas para este día.</div>';
            return;
        }

        for (const ev of dayEvents) {
            const taskItem = document.createElement('div');
            taskItem.className = 'info-item cursor-pointer hover:bg-slate-700/30 rounded p-2 transition-colors';

            // Determinar color por estado
            let statusColor = 'text-slate-400';
            const status = ev.extendedProps?.status;
            switch(status) {
                case 'completed': statusColor = 'text-green-400'; break;
                case 'overdue': statusColor = 'text-red-400'; break;
                case 'in_progress': statusColor = 'text-blue-400'; break;
                case 'pending': statusColor = 'text-yellow-400'; break;
            }

            taskItem.innerHTML = `
                <div>
                    <p class="info-value ${statusColor}" style="text-align: left;">
                        ${formatTimeFromEvent(ev)} - ${ev.title || 'Tarea sin título'}
                    </p>
                    <p class="info-label" style="min-width: 0;">
                        ${ev.extendedProps?.description || 'Sin descripción'}
                    </p>
                </div>
            `;

            taskItem.addEventListener('click', () => {
                if (typeof openTaskDetailsModal === 'function') {
                    openTaskDetailsModal(ev.id);
                } else {
                    console.log('Abriendo detalles de tarea:', ev.id);
                }
            });

            miniCalTasksList.appendChild(taskItem);
        }
    }

    async function loadMiniCalendar() {
        await fetchMiniEvents(miniViewDate);
        renderMiniCalendar(miniViewDate);

        // Seleccionar hoy por defecto si no hay fecha seleccionada
        if (!selectedDate) {
            selectedDate = fmtDate(new Date());
        }
        showMiniDayTasks(selectedDate);
    }

    // Inicializar
    loadMiniCalendar();
});
</script>

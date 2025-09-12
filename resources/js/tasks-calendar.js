// JavaScript para el calendario de tareas
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Calendario: Iniciando...');

    const titleEl = document.getElementById('cal-title');
    const cellsContainer = document.getElementById('cal-cells');
    const cellTpl = document.getElementById('cal-cell-template');
    const taskBarsOverlay = document.getElementById('task-bars-overlay');
    const extendedTaskBarTpl = document.getElementById('extended-task-bar-template');
    const btnPrev = document.getElementById('cal-prev');
    const btnNext = document.getElementById('cal-next');
    const btnToday = document.getElementById('cal-today');

    console.log('📍 Elementos encontrados:', {
        titleEl: !!titleEl,
        cellsContainer: !!cellsContainer,
        cellTpl: !!cellTpl,
        taskBarsOverlay: !!taskBarsOverlay,
        extendedTaskBarTpl: !!extendedTaskBarTpl,
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
            const base = window.taskData?.apiTasks || '/api/tasks-laravel/calendar';
            const url = new URL(base, window.location.origin);
            url.search = params.toString();

            console.log('🌐 Fetching eventos desde:', url.toString());

            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            };
            if (window.authToken) {
                headers['Authorization'] = `Bearer ${window.authToken}`;
            }

            const res = await fetch(url, {
                headers,
                credentials: 'include'
            });

            console.log('📡 Respuesta recibida:', res.status, res.statusText);

            const data = await res.json();
            console.log('📦 Datos recibidos:', data);

            eventsByDate = {};

            const events = Array.isArray(data) ? data : [];
            console.log('📋 Procesando', events.length, 'eventos');

            for (const ev of events){
                if (!ev.start) {
                    console.warn('⚠️ Evento sin fecha de inicio:', ev);
                    continue;
                }
                const day = ev.start.substring(0,10);
                if (!eventsByDate[day]) eventsByDate[day] = [];
                eventsByDate[day].push(ev);
                console.log(`✅ Evento agregado para ${day}:`, ev.title || 'Sin título');
            }

            console.log('📊 Eventos organizados por fecha:', eventsByDate);

        } catch (error) {
            console.error('❌ Error fetching calendar events:', error);
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
        if (taskBarsOverlay) taskBarsOverlay.innerHTML = '';

        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        const startWeekday = (firstDay.getDay()); // 0=Dom
        const daysInMonth = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();
        const totalCells = 42;
        const todayStr = fmtDate(new Date());
        const today = new Date();
        console.log('📆 Fecha de HOY detectada:', todayStr);
        const prevMonthDays = startWeekday;
        const prevMonthLastDate = new Date(date.getFullYear(), date.getMonth(), 0).getDate();

        console.log('Creando', totalCells, 'celdas...');

        // Mapa para almacenar información de las celdas
        const cellMap = new Map();

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

            // Almacenar información de la celda
            cellMap.set(iso, {
                index: i,
                element: cell.firstElementChild,
                date: cellDate,
                row: Math.floor(i / 7),
                col: i % 7
            });

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

        console.log('✅ Celdas creadas. Renderizando barras extendidas...');

        // Renderizar barras extendidas para tareas
        if (taskBarsOverlay) {
            renderExtendedTaskBars(cellMap, today, todayStr);
        }

        console.log('✅ Calendario renderizado exitosamente');
    }

    function renderExtendedTaskBars(cellMap, today, todayStr) {
        console.log('🎨 Renderizando barras extendidas...');

        // Crear un mapa de tareas por fecha para evitar duplicados
        const processedTasks = new Set();

        // Contador de tareas por celda para apilar verticalmente
        const taskCountByCell = new Map();

        for (const [dateStr, events] of Object.entries(eventsByDate)) {
            for (const event of events) {
                // Evitar duplicados basándose en id único
                const taskId = event.id || `${event.title}-${event.start}`;
                if (processedTasks.has(taskId)) continue;
                processedTasks.add(taskId);

                // Obtener fecha límite de la tarea
                const taskEndDate = new Date(event.end || event.start);

                // Solo mostrar tareas que terminen HOY o en el futuro
                if (taskEndDate < today) continue;

                console.log(`📊 Procesando tarea: ${event.title}`);
                console.log(`📅 Desde HOY (${todayStr}) hasta fecha límite: ${fmtDate(taskEndDate)}`);

                // Calcular duración desde HOY hasta fecha límite
                const duration = calculateTaskDuration(today, taskEndDate, cellMap);
                if (duration.isValid) {
                    // Contar tareas en la celda de inicio para apilar
                    const startCellKey = `${duration.startRow}-${duration.startCol}`;
                    const taskIndex = taskCountByCell.get(startCellKey) || 0;
                    taskCountByCell.set(startCellKey, taskIndex + 1);

                    createExtendedTaskBar(event, duration, today, taskIndex);
                }
            }
        }
    }

    function calculateTaskDuration(startDate, endDate, cellMap) {
        const startStr = fmtDate(startDate);
        const endStr = fmtDate(endDate);

        const startCell = cellMap.get(startStr);
        const endCell = cellMap.get(endStr);

        if (!startCell || !endCell) {
            console.warn(`⚠️ No se encontraron celdas para el rango ${startStr} -> ${endStr}`);
            return { isValid: false };
        }

        // Calcular días de diferencia
        const timeDiff = endDate.getTime() - startDate.getTime();
        const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

        console.log(`📏 Duración calculada: ${daysDiff} días (${startStr} -> ${endStr})`);

        return {
            isValid: true,
            startRow: startCell.row,
            startCol: startCell.col,
            endRow: endCell.row,
            endCol: endCell.col,
            daysDiff: daysDiff,
            startCell: startCell,
            endCell: endCell
        };
    }

    function createExtendedTaskBar(event, duration, startDate, taskIndex = 0) {
        if (!extendedTaskBarTpl) return;

        // Crear barras para cada fila si la tarea se extiende por múltiples filas
        const gridCols = 7;
        const cellWidth = 100 / gridCols;

        if (duration.startRow === duration.endRow) {
            // Misma fila: crear una sola barra
            createSingleTaskBar(event, duration, cellWidth, gridCols, taskIndex);
        } else {
            // Múltiples filas: crear barras para cada fila
            // Primera fila
            const firstRowDuration = {
                ...duration,
                endCol: gridCols - 1,
                endRow: duration.startRow
            };
            createSingleTaskBar(event, firstRowDuration, cellWidth, gridCols, taskIndex);

            // Filas intermedias (si las hay)
            for (let row = duration.startRow + 1; row < duration.endRow; row++) {
                const middleRowDuration = {
                    ...duration,
                    startRow: row,
                    startCol: 0,
                    endRow: row,
                    endCol: gridCols - 1
                };
                createSingleTaskBar(event, middleRowDuration, cellWidth, gridCols, 0); // Sin offset en filas intermedias
            }

            // Última fila
            if (duration.endRow > duration.startRow) {
                const lastRowDuration = {
                    ...duration,
                    startRow: duration.endRow,
                    startCol: 0,
                    endRow: duration.endRow
                };
                createSingleTaskBar(event, lastRowDuration, cellWidth, gridCols, 0); // Sin offset en última fila
            }
        }
    }

    function createSingleTaskBar(event, duration, cellWidth, gridCols, taskIndex = 0) {
        if (!extendedTaskBarTpl) return;

        const barElement = extendedTaskBarTpl.content.cloneNode(true);
        const bar = barElement.querySelector('.extended-task-bar');
        const title = barElement.querySelector('.task-title');
        const time = barElement.querySelector('.task-time');

        // Configurar contenido
        title.textContent = event.title || 'Sin título';
        time.textContent = formatTimeFromEvent(event);

        // Aplicar clases de estado
        bar.classList.add(getStatusClass(event));

        // Calcular posición y tamaño
        const leftPercent = duration.startCol * cellWidth;
        const widthCells = duration.endCol - duration.startCol + 1;
        const widthPercent = widthCells * cellWidth;

        // Calcular posición vertical con apilamiento
        const baseTop = duration.startRow * (100/6); // 6 filas del calendario
        const barHeight = 18; // Altura de cada barra en px
        const barSpacing = 2; // Espaciado entre barras
        const verticalOffset = taskIndex * (barHeight + barSpacing);

        // Aplicar estilos
        bar.style.left = `${leftPercent}%`;
        bar.style.width = `${widthPercent}%`;
        bar.style.top = `calc(${baseTop}% + ${30 + verticalOffset}px)`; // 30px offset inicial + apilamiento
        bar.style.zIndex = `${10 + taskIndex}`;
        bar.style.height = `${barHeight}px`;
        bar.style.fontSize = '11px';
        bar.style.borderRadius = '6px';

        console.log(`🎯 Barra creada: ${event.title} - Fila: ${duration.startRow}, Col: ${duration.startCol}-${duration.endCol}, Índice: ${taskIndex}`);

        // Agregar al overlay
        taskBarsOverlay.appendChild(barElement);
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

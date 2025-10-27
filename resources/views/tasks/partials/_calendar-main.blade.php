<!-- Calendario mensual dinámico -->
<div class="bg-slate-900/50 border border-slate-700/50 rounded-xl p-4 hidden">
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
        <div class="relative">
            <div id="cal-cells" class="grid grid-cols-7 bg-slate-900/30"></div>
            <!-- Overlay para barras extendidas -->
            <div id="task-bars-overlay" class="absolute inset-0 pointer-events-none"></div>
        </div>
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

<template id="extended-task-bar-template">
    <div class="extended-task-bar absolute h-6 rounded-lg text-white text-xs font-medium flex items-center px-2 cursor-pointer hover:opacity-90 transition-opacity z-10 pointer-events-auto">
        <span class="task-title truncate flex-1"></span>
        <span class="task-time ml-2 text-[10px] opacity-75"></span>
    </div>
</template>

@vite(['resources/css/tasks-calendar.css', 'resources/js/tasks-calendar.js'])

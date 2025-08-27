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
    <div id="day-tasks-panel" class="mt-6 hidden">
        <div class="text-slate-300 font-semibold mb-2" id="day-title"></div>
        <div id="day-task-list" class="flex flex-col gap-2"></div>
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


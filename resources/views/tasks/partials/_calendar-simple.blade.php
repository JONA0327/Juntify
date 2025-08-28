<!-- Calendario mensual simple para testing -->
<div class="bg-slate-900/50 border border-slate-700/50 rounded-xl p-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button onclick="alert('Anterior')" class="btn btn-secondary">Anterior</button>
            <button onclick="alert('Hoy')" class="btn btn-secondary">Hoy</button>
            <button onclick="alert('Siguiente')" class="btn btn-secondary">Siguiente</button>
        </div>
        <div class="text-lg font-semibold text-slate-200">Agosto 2025</div>
        <div class="hidden md:block text-slate-400 text-sm">Calendario de prueba</div>
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
        <div class="grid grid-cols-7 bg-slate-900/30">
            <!-- Fila 1 -->
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-600">27</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-600">28</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-600">29</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-600">30</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-600">31</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">1</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">2</span>
            </div>

            <!-- Días del mes actual -->
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">3</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">4</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">5</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">6</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">7</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">8</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">9</span>
            </div>

            <!-- Más días... (simplificado) -->
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">10</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">11</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">12</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">13</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">14</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">15</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">16</span>
            </div>

            <!-- Día actual (27) -->
            <div class="bg-green-500/10 ring-2 ring-green-400/50 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-bold text-green-300">27</span>
                <span class="text-[8px] px-1 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-400/30">Hoy</span>
                <div class="mt-2">
                    <div class="bg-blue-500 h-4 rounded text-white text-[10px] px-1 flex items-center">Tarea ejemplo</div>
                </div>
            </div>

            <!-- Resto de días -->
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">28</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">29</span>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-r border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">30</span>
                <div class="mt-2">
                    <div class="bg-yellow-500 h-4 rounded text-white text-[10px] px-1 flex items-center">Investigar el pro...</div>
                </div>
            </div>
            <div class="bg-slate-900/60 h-24 p-1.5 relative border-b border-slate-700/30">
                <span class="text-sm font-medium text-slate-300">31</span>
            </div>
        </div>
    </div>
</div>

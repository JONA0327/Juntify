<!-- Contenedor de Pestañas de Reuniones (estilo Reuniones) -->
<div class="info-card">
    <nav class="mb-6">
        <ul class="flex gap-3">
            <li>
                <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="my-meetings">Mis reuniones</button>
            </li>
            <li>
                <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="shared-meetings">Reuniones compartidas</button>
            </li>
            <li>
                <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="containers">Contenedores</button>
            </li>
            <li>
                <button class="tab-transition px-4 py-2 rounded-lg bg-slate-800/50 border border-slate-700/50 text-slate-200 hover:bg-slate-700/50" data-target="contacts">Contactos</button>
            </li>
        </ul>
    </nav>

    <div id="meetings-container" class="fade-in stagger-2">
        <div id="my-meetings" class="hidden">
            <div class="meetings-grid" id="my-meetings-grid"></div>
        </div>
        <div id="shared-meetings" class="hidden">
            <div class="meetings-grid" id="shared-meetings-grid"></div>
        </div>
        <div id="containers" class="hidden">
            <div class="meetings-grid" id="containers-grid"></div>
        </div>
        <div id="contacts" class="hidden">
            @include('contacts.index')
        </div>
    </div>

</div>



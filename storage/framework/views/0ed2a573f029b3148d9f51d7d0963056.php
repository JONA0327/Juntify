<div id="shareModal" class="modal hidden" style="z-index: 9999;">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="modal-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="modal-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                </svg>
                Compartir Reunión
            </h3>
            <button class="modal-close-btn" onclick="closeShareModal()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Columna izquierda: descripción -->
                <div class="md:col-span-1">
                    <p class="text-slate-300 leading-relaxed">
                        Selecciona los contactos con los que deseas compartir esta reunión.
                    </p>
                </div>

                <!-- Columna derecha: búsqueda, lista, seleccionados y mensaje -->
                <div class="md:col-span-2 space-y-4">
                    <!-- Buscar contactos -->
                    <div class="space-y-2">
                        <label class="form-label" for="shareModal-contactSearch">Buscar contactos</label>
                        <div class="contact-search">
                            <span class="search-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input type="text" id="shareModal-contactSearch" class="modal-input pl-10" placeholder="Buscar por nombre o email..." autocomplete="off">
                        </div>
                    </div>

                    <!-- Lista de contactos -->
                    <div class="space-y-2">
                        <span class="form-label" id="shareModal-contactsList-label">Contactos disponibles</span>
                        <div id="shareModal-contactsList" class="h-64 overflow-y-auto border border-slate-600 rounded-lg bg-slate-800" role="listbox" aria-labelledby="shareModal-contactsList-label">
                            <div class="p-4 text-center text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 002-2v-2a2 2 0 012-2h2m8 0h2a2 2 0 012 2v2a2 2 0 01-2 2h-2m-8 0H6a2 2 0 01-2-2v-2a2 2 0 012-2h2m0 0V6a2 2 0 012-2h2a2 2 0 012 2v2" />
                                </svg>
                                Cargando contactos...
                            </div>
                        </div>
                    </div>

                    <!-- Contactos seleccionados -->
                    <div id="selectedContactsContainer" class="hidden">
                        <span class="form-label" id="selectedContactsLabel">Contactos seleccionados</span>
                        <div id="selectedContacts" class="flex flex-wrap gap-2" role="list" aria-labelledby="selectedContactsLabel"></div>
                    </div>

                    <!-- Mensaje opcional -->
                    <div>
                        <label class="form-label" for="shareModal-shareMessage">Mensaje (opcional)</label>
                        <textarea id="shareModal-shareMessage" class="modal-input" rows="3" placeholder="Añade un mensaje para acompañar la invitación..." autocomplete="off"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeShareModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="confirmShare" onclick="confirmShare()" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                Compartir
            </button>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\Juntify\resources\views/components/share-modal.blade.php ENDPATH**/ ?>
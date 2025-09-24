<!-- Modal de creación de carpeta principal eliminado: ahora la estructura es automática -->

<!-- Modal de carga -->
<div class="modal" id="drive-loading-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="modal-icon">⏳</span>
                Conectando con Google Drive
            </h3>
        </div>
        <div class="modal-body">
            <p class="modal-description">
                Por favor espera mientras verificamos tu conexión...
            </p>
        </div>
    </div>
</div>

<!-- Modal eliminar cuenta -->
<div class="modal" id="delete-account-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="modal-icon">🗑️</span>
                Eliminar Cuenta
            </h3>
        </div>
        <form method="POST" action="{{ route('account.delete') }}" onsubmit="return confirmDeleteAccount(event)">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="modal-description mb-4">
                    Esta acción eliminará <strong>definitivamente</strong> tu cuenta y todos tus datos asociados.
                    Escribe tu nombre de usuario <strong>{{ auth()->user()->username }}</strong> para confirmar.
                </p>
                <input type="text" name="confirmation" class="input w-full" placeholder="{{ auth()->user()->username }}" required />
                <label class="flex items-center gap-2 mt-4 text-sm">
                    <input type="checkbox" name="delete_drive" value="1" checked />
                    Borrar carpeta raíz de Drive (si existe)
                </label>
                <div class="warning-box mt-4">
                    Al continuar, se borrarán reuniones, tareas, tokens, documentos AI, contactos y compartidos.
                </div>
            </div>
            <div class="modal-footer flex gap-3">
                <button type="button" class="btn btn-secondary flex-1" onclick="closeModal('delete-account-modal')">Cancelar</button>
                <button type="submit" class="btn btn-danger flex-1">Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDeleteAccount(e){
    const input = e.target.querySelector('input[name="confirmation"]').value.trim().toUpperCase();
    const expected = "{{ strtoupper(auth()->user()->username) }}";
    if(input !== expected){
        alert('El texto no coincide. Debes escribir exactamente tu nombre de usuario.');
        e.preventDefault();
        return false;
    }
    return true;
}
</script>

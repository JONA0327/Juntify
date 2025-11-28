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

<!-- Modal: Drive bloqueado por plan -->
<div class="modal" id="drive-locked-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <span class="modal-icon">🔒</span>
                Función disponible en planes superiores
            </h3>
        </div>
        <div class="modal-body">
            <p class="modal-description">
                La conexión con Google Drive está disponible únicamente para los planes <strong>Business</strong> y <strong>Enterprise</strong> (incluye roles internos Developer y Superadmin).
                Tus reuniones se guardarán temporalmente durante <strong><span id="drive-locked-retention"></span></strong> antes de eliminar el audio.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('drive-locked-modal')">Entendido</button>
            <a href="/profile#section-plans" class="btn btn-primary">Ver planes disponibles</a>
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
        <form method="POST" action="{{ route('account.delete') }}" onsubmit="return confirmDeleteAccount(event)" data-expected-username="{{ strtoupper(auth()->user()->username) }}">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="modal-description mb-4">
                    Esta acción eliminará <strong>definitivamente</strong> tu cuenta y todos tus datos asociados.
                    Escribe tu nombre de usuario <strong>{{ auth()->user()->username }}</strong> para confirmar.
                </p>
                <input type="text" name="confirmation" class="modal-input" placeholder="{{ auth()->user()->username }}" required />
                <label class="flex items-center gap-2 mt-4 text-sm">
                    <input type="checkbox" name="delete_drive" value="1" checked />
                    Borrar carpeta raíz de Drive (si existe)
                </label>
                <div class="warning-box mt-4">
                    Al continuar, se borrarán reuniones, tareas, tokens, documentos AI, contactos y compartidos.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-account-modal')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de éxito de pago -->
@if(session('payment_success'))
<div class="modal modal-success active" id="payment-success-modal">
    <div class="modal-content modal-payment-success">
        <div class="modal-header modal-header-success">
            <div class="success-icon">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
            </div>
            <h3 class="modal-title">¡Pago Exitoso!</h3>
        </div>
        <div class="modal-body">
            <p class="modal-description">
                Tu suscripción ha sido activada correctamente
            </p>

            @if(session('payment_plan'))
            <div class="payment-details">
                <div class="detail-item">
                    <span class="detail-label">Plan:</span>
                    <span class="detail-value">{{ session('payment_plan') }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Monto:</span>
                    <span class="detail-value">${{ session('payment_amount') }} {{ session('payment_currency', 'MXN') }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value status-approved">Aprobado</span>
                </div>
                @if(session('payment_simulated'))
                <div class="detail-item">
                    <span class="detail-label">Modo:</span>
                    <span class="detail-value simulation-badge">Simulado</span>
                </div>
                @endif
            </div>
            @endif

            <div class="success-message">
                <p><strong>¿Qué sigue?</strong></p>
                <p>Ya puedes disfrutar de todas las funcionalidades de tu nuevo plan. Tu suscripción estará activa inmediatamente.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-success" onclick="closePaymentSuccessModal()">
                Continuar
            </button>
        </div>
    </div>
</div>




@endif



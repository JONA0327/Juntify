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

<style>
.modal-success {
    backdrop-filter: blur(8px);
    background: rgba(0, 0, 0, 0.5);
}

.modal-payment-success {
    max-width: 500px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    text-align: center;
    padding: 2rem 1.5rem 1.5rem;
    border-bottom: none;
}

.success-icon {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.checkmark {
    width: 32px;
    height: 32px;
    color: white;
}

.modal-header-success .modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.payment-details {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 500;
    color: #64748b;
}

.detail-value {
    font-weight: 600;
    color: #1e293b;
}

.status-approved {
    color: #059669 !important;
}

.simulation-badge {
    background: #fef3c7;
    color: #92400e;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.success-message {
    background: #ecfdf5;
    border: 1px solid #d1fae5;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.success-message p {
    margin: 0 0 0.5rem;
    color: #065f46;
}

.success-message p:last-child {
    margin-bottom: 0;
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    width: 100%;
    transition: all 0.2s;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}
</style>

<script>
function closePaymentSuccessModal() {
    const modal = document.getElementById('payment-success-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}
</script>
@endif

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

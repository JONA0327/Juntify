<!-- Sección de Mis Compras -->
<section id="section-purchases" class="content-section" data-section="purchases" style="display: none;">
    <div class="section-header">
        <h2 class="section-title">Historial de Compras</h2>
        <p class="section-subtitle">Revisa todas tus compras y suscripciones</p>
    </div>

    <div class="purchases-container">
        @if($userPayments && $userPayments->count() > 0)
            <div class="purchases-grid">
                @foreach($userPayments as $payment)
                    <div class="purchase-card {{ $payment->status == 'approved' ? 'approved' : ($payment->status == 'pending' ? 'pending' : 'rejected') }}">
                        <div class="purchase-header">
                            <div class="purchase-info">
                                <h3 class="purchase-title">Factura #{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</h3>
                                <div class="purchase-meta">
                                    <span class="purchase-date">{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y H:i') }}</span>
                                    @if($payment->external_payment_id)
                                        <span class="purchase-external">Ref: {{ $payment->external_payment_id }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="purchase-status">
                                @if($payment->status == 'approved')
                                    <span class="status-badge success">
                                        <svg class="status-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        Aprobado
                                    </span>
                                @elseif($payment->status == 'pending')
                                    <span class="status-badge warning">
                                        <svg class="status-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        Pendiente
                                    </span>
                                @else
                                    <span class="status-badge error">
                                        <svg class="status-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        Rechazado
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="purchase-details">
                            <div class="purchase-amount">
                                <span class="amount-label">Monto:</span>
                                <span class="amount-value">${{ number_format($payment->amount, 2) }} {{ $payment->currency ?? 'MXN' }}</span>
                            </div>

                            @if($payment->payment_method)
                                <div class="purchase-method">
                                    <span class="method-label">Método:</span>
                                    <span class="method-value">{{ ucfirst($payment->payment_method) }}</span>
                                </div>
                            @endif

                            @if($payment->external_payment_id)
                                <div class="purchase-external">
                                    <span class="external-label">ID de Pago:</span>
                                    <span class="external-value">{{ $payment->external_payment_id }}</span>
                                </div>
                            @endif

                            @if($payment->description)
                                <div class="purchase-description">
                                    <span class="description-label">Descripción:</span>
                                    <span class="description-value">{{ $payment->description }}</span>
                                </div>
                            @endif
                        </div>

                        @if($payment->status == 'approved')
                            <div class="purchase-actions">
                                <button class="btn btn-outline btn-sm" onclick="downloadReceipt({{ $payment->id }})">
                                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Descargar Recibo
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Paginación si es necesario -->
            @if(method_exists($userPayments, 'hasPages') && $userPayments->hasPages())
                <div class="purchases-pagination">
                    {{ $userPayments->links() }}
                </div>
            @endif
        @else
            <div class="empty-state">
                <div class="empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .96.36 1.07.86l.507 2.454M7.5 14.25v.75m0-.75h12.75m0 0l-.882-4.5m-.882-4.5H7.5M7.5 14.25H5.25A2.25 2.25 0 003 12v-6.5A2.25 2.25 0 005.25 3.5h11.25c.331 0 .647.072.932.205" />
                    </svg>
                </div>
                <h3 class="empty-title">No tienes compras registradas</h3>
                <p class="empty-description">Cuando realices tu primera compra, aparecerá aquí el historial completo.</p>
                <button class="btn btn-primary" onclick="document.querySelector('.nav-link[data-section=&quot;plans&quot;]').click()">
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.639 5.033a1 1 0 00.95.69h5.287c.969 0 1.371 1.24.588 1.81l-4.278 3.11a1 1 0 00-.364 1.118l1.64 5.034c.3.921-.755 1.688-1.54 1.118l-4.279-3.11a1 1 0 00-1.175 0l-4.279 3.11c-.784.57-1.838-.197-1.539-1.118l1.639-5.034a1 1 0 00-.364-1.118l-4.278-3.11c-.783-.57-.38-1.81.588-1.81h5.287a1 1 0 00.951-.69l1.639-5.034z" />
                    </svg>
                    Ver Planes Disponibles
                </button>
            </div>
        @endif
    </div>
</section>





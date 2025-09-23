const STATUS_LABELS = {
    approved: { title: '¡Pago exitoso!', tone: 'success' },
    pending: { title: 'Pago pendiente', tone: 'pending' },
    in_process: { title: 'Pago en revisión', tone: 'pending' },
    rejected: { title: 'Pago rechazado', tone: 'error' },
    cancelled: { title: 'Pago cancelado', tone: 'error' },
    refunded: { title: 'Pago reintegrado', tone: 'pending' },
    charged_back: { title: 'Pago en disputa', tone: 'pending' },
};

const TONE_STYLES = {
    success: {
        border: '#16a34a',
        background: '#dcfce7',
    },
    pending: {
        border: '#ca8a04',
        background: '#fef08a',
    },
    error: {
        border: '#dc2626',
        background: '#fee2e2',
    },
};

function ensureModal() {
    let modal = document.getElementById('mercado-pago-status-modal');

    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.id = 'mercado-pago-status-modal';
    modal.style.position = 'fixed';
    modal.style.inset = '0';
    modal.style.display = 'none';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.backgroundColor = 'rgba(17, 24, 39, 0.45)';
    modal.style.zIndex = '1000';

    modal.innerHTML = `
        <div id="mercado-pago-status-card" style="max-width: 360px;width: 90%;padding: 24px;border-radius: 16px;background: #ffffff;box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);border: 3px solid transparent;">
            <div style="display: flex;justify-content: space-between;align-items: center;">
                <h2 id="mercado-pago-status-title" style="font-size: 1.25rem;font-weight: 700;color: #111827;margin: 0;">Estado del pago</h2>
                <button type="button" id="mercado-pago-status-close" style="border:none;background:transparent;font-size:1.5rem;line-height:1;color:#4b5563;cursor:pointer;">×</button>
            </div>
            <p id="mercado-pago-status-message" style="margin-top: 12px;font-size: 1rem;color: #374151;">Estamos revisando tu pago.</p>
        </div>
    `;

    document.body.appendChild(modal);

    modal.querySelector('#mercado-pago-status-close').addEventListener('click', () => {
        hideModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            hideModal();
        }
    });

    return modal;
}

function hideModal() {
    const modal = document.getElementById('mercado-pago-status-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function showModal(status, message) {
    const modal = ensureModal();
    const label = STATUS_LABELS[status] ?? { title: 'Estado del pago', tone: 'pending' };
    const toneStyle = TONE_STYLES[label.tone] ?? TONE_STYLES.pending;

    const card = modal.querySelector('#mercado-pago-status-card');
    const title = modal.querySelector('#mercado-pago-status-title');
    const body = modal.querySelector('#mercado-pago-status-message');

    if (card) {
        card.style.borderColor = toneStyle.border;
        card.style.background = toneStyle.background;
    }

    if (title) {
        title.textContent = label.title;
    }

    if (body) {
        body.textContent = message ?? resolveDefaultMessage(status);
    }

    modal.style.display = 'flex';
}

function resolveDefaultMessage(status) {
    switch (status) {
        case 'approved':
            return 'Tu pago fue acreditado correctamente. ¡Gracias por tu compra!';
        case 'pending':
        case 'in_process':
            return 'Estamos revisando la información del pago. Te avisaremos cuando se acredite.';
        case 'rejected':
            return 'El pago fue rechazado. Revisa los datos de tu tarjeta o intenta con otro medio de pago.';
        case 'cancelled':
            return 'El pago fue cancelado por el emisor o por ti. Si fue un error, intenta nuevamente.';
        default:
            return 'Recibimos una actualización del pago. Verifica el detalle de tu compra.';
    }
}

function startPolling(url, interval, onFinalStatus) {
    const effectiveInterval = Math.max(interval, 3000);
    let timerId = null;

    const poll = async () => {
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Estado HTTP inesperado: ${response.status}`);
            }

            const payload = await response.json();
            const status = payload.status;

            if (status) {
                showModal(status, payload.message);

                if (status === 'approved' || status === 'rejected' || status === 'cancelled') {
                    if (timerId) {
                        clearInterval(timerId);
                    }
                    if (typeof onFinalStatus === 'function') {
                        onFinalStatus(status, payload);
                    }
                }
            }
        } catch (error) {
            console.error('No fue posible consultar el estado del pago', error);
            if (timerId) {
                clearInterval(timerId);
            }
            showModal('rejected', 'No fue posible obtener el estado del pago. Actualiza la página para reintentar.');
        }
    };

    poll();
    timerId = setInterval(poll, effectiveInterval);

    return () => {
        if (timerId) {
            clearInterval(timerId);
        }
    };
}

export function initMercadoPagoStatusPolling() {
    const nodes = document.querySelectorAll('[data-mercado-pago-status-url]');
    if (!nodes.length) {
        return;
    }

    nodes.forEach((node) => {
        const url = node.getAttribute('data-mercado-pago-status-url');
        if (!url) {
            return;
        }

        const intervalAttr = node.getAttribute('data-mercado-pago-interval');
        const interval = intervalAttr ? parseInt(intervalAttr, 10) : 5000;

        startPolling(url, Number.isFinite(interval) ? interval : 5000, (status) => {
            const event = new CustomEvent('mercado-pago:status', {
                detail: { status, url },
            });
            window.dispatchEvent(event);
        });
    });
}

export function showMercadoPagoStatus(status, message) {
    showModal(status, message);
}

if (typeof window !== 'undefined') {
    window.showMercadoPagoStatus = showMercadoPagoStatus;
}

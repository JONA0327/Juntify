<script>
// Global variables injection (shared partial)
// Safely sets window.userRole and window.currentOrganizationId if not already defined.
(function(){
    try {
        if (typeof window.userRole === 'undefined' || window.userRole === null) {
            @if(isset($user))
                window.userRole = @json($user->roles ?? null);
            @else
                window.userRole = window.userRole || null;
            @endif
        }
        if (typeof window.currentOrganizationId === 'undefined' || window.currentOrganizationId === null) {
            @if(isset($organizationId))
                window.currentOrganizationId = @json($organizationId);
            @elseif(isset($organizations) && count($organizations) > 0)
                // Pick first organization id as a fallback (used on organization page)
                window.currentOrganizationId = @json(optional($organizations->first())->id);
            @else
                window.currentOrganizationId = window.currentOrganizationId || null;
            @endif
        }
        // Also expose user id for convenience
        if (typeof window.authUserId === 'undefined') {
            @if(auth()->check())
                window.authUserId = @json(auth()->id());
            @else
                window.authUserId = null;
            @endif
        }
        if (typeof window.authUsername === 'undefined') {
            @if(auth()->check())
                window.authUsername = @json(auth()->user()->username);
            @else
                window.authUsername = null;
            @endif
        }
        // Provide dataset fallbacks for scripts that look at body dataset
        if (document && document.body) {
            if (!document.body.dataset.userRole && window.userRole) {
                document.body.dataset.userRole = window.userRole;
            }
            if (!document.body.dataset.organizationId && window.currentOrganizationId) {
                document.body.dataset.organizationId = window.currentOrganizationId;
            }
        }
        // Variables del plan del usuario y pertenencia a organización
        @php
            $planService = app(\App\Services\PlanLimitService::class);
            $driveAllowed = auth()->check() ? $planService->userCanUseDrive(auth()->user()) : false;
            $tempRetention = auth()->check() ? $planService->getTemporaryRetentionDays(auth()->user()) : 7;
        @endphp

        if (typeof window.userPlanCode === 'undefined') {
            @if(auth()->check())
                window.userPlanCode = @json(auth()->user()->plan_code ?? 'free');
            @else
                window.userPlanCode = 'free';
            @endif
        }

        if (typeof window.userBelongsToOrganization === 'undefined') {
            // Un usuario pertenece a organización si tiene currentOrganizationId o si tiene roles organizacionales
            window.userBelongsToOrganization = !!(window.currentOrganizationId ||
                (window.userRole && ['admin', 'superadmin', 'founder', 'developer'].includes(window.userRole)));
        }

        if (typeof window.userCanUseDrive === 'undefined') {
            window.userCanUseDrive = @json($driveAllowed);
        }

        if (typeof window.tempRetentionDays === 'undefined') {
            window.tempRetentionDays = @json($tempRetention);
        }

        // Función helper para verificar si el usuario tiene acceso a funciones premium
        window.hasPremiumAccess = function() {
            return !!window.userCanUseDrive;
        };

        // Función específica para verificar si el usuario puede crear contenedores
        window.canCreateContainers = function() {
            const planCode = (window.userPlanCode || '').toString().toLowerCase();
            const role = (window.userRole || '').toString().toLowerCase();

            console.log('🚀 [canCreateContainers] Iniciando verificación:', {
                planCode: planCode || 'UNDEFINED',
                role: role || 'UNDEFINED',
                userBelongsToOrganization: window.userBelongsToOrganization
            });

            // Si pertenece a una organización, puede crear contenedores
            if (window.userBelongsToOrganization) {
                console.log('✅ [canCreateContainers] Acceso aprobado por organización');
                return true;
            }

            // Verificación específica para BASIC
            if (role === 'basic' || planCode === 'basic' || planCode === 'basico') {
                console.log('✅ [canCreateContainers] Usuario BASIC detectado - PUEDE crear contenedores');
                return true;
            }

            // Plan FREE no puede crear contenedores
            const freePlans = ['free', 'freemium'];
            const isFree = freePlans.includes(role) || freePlans.includes(planCode);

            if (isFree) {
                console.log('❌ [canCreateContainers] Usuario FREE - NO puede crear contenedores');
                return false;
            }

            // Planes superiores pueden crear contenedores
            const premiumPlans = ['negocios', 'business', 'enterprise', 'founder', 'developer', 'superadmin'];
            const isPremium = premiumPlans.includes(role) || premiumPlans.includes(planCode);

            if (isPremium) {
                console.log('✅ [canCreateContainers] Usuario PREMIUM - PUEDE crear contenedores');
                return true;
            }

            // Por defecto, denegar acceso
            console.log('❌ [canCreateContainers] Acceso denegado por defecto');
            return false;
        };

        // Función para obtener límites de contenedores por plan
        window.getContainerLimits = function() {
            const planCode = (window.userPlanCode || '').toString().toLowerCase();
            const role = (window.userRole || '').toString().toLowerCase();

            // Si pertenece a una organización, límites amplios
            if (window.userBelongsToOrganization) {
                return {
                    maxContainers: 50,
                    maxMeetingsPerContainer: 100
                };
            }

            // Plan BASIC: 3 contenedores, 10 reuniones por contenedor
            const basicPlans = ['basic', 'basico'];
            const isBasicByPlan = basicPlans.some(value => value && (planCode === value || planCode.includes(value)));
            const isBasicByRole = basicPlans.some(value => value && (role === value || role.includes(value)));

            if (isBasicByPlan || isBasicByRole) {
                return {
                    maxContainers: 3,
                    maxMeetingsPerContainer: 10
                };
            }

            // Planes superiores: límites altos
            const premiumPlans = ['negocios', 'business', 'enterprise', 'founder', 'developer', 'superadmin'];
            const isPremiumByPlan = premiumPlans.some(value => value && (planCode === value || planCode.includes(value)));
            const isPremiumByRole = premiumPlans.some(value => value && (role === value || role.includes(value)));

            if (isPremiumByPlan || isPremiumByRole) {
                return {
                    maxContainers: 999, // Prácticamente ilimitado
                    maxMeetingsPerContainer: 999
                };
            }

            // Plan FREE: sin contenedores
            return {
                maxContainers: 0,
                maxMeetingsPerContainer: 0
            };
        };

        // Función para verificar si puede crear más contenedores
        window.canCreateMoreContainers = function(currentContainerCount = 0) {
            const limits = window.getContainerLimits();
            const canCreateBasic = window.canCreateContainers();

            console.log('🔍 Verificando límite de contenedores:', {
                currentCount: currentContainerCount,
                maxAllowed: limits.maxContainers,
                canCreateBasic,
                canCreateMore: canCreateBasic && currentContainerCount < limits.maxContainers
            });

            return canCreateBasic && currentContainerCount < limits.maxContainers;
        };

        // Función global showUpgradeModal
        if (typeof window.showUpgradeModal === 'undefined') {
            const ensureModalEventListeners = (modal) => {
                if (!modal || modal._listenersAdded) {
                    return;
                }

                const overlay = modal.querySelector('.modal-overlay');
                const closeBtn = modal.querySelector('#modal-close-btn');
                const cancelBtn = modal.querySelector('#modal-cancel-btn');
                const plansBtn = modal.querySelector('#modal-plans-btn');
                const content = modal.querySelector('.modal-content');

                modal._overlayHandler = function(e) {
                    console.log('🔒 Click en overlay - cerrando modal');
                    window.closeUpgradeModal();
                };

                modal._closeBtnHandler = function(e) {
                    console.log('🔒 Click en X - cerrando modal');
                    e.preventDefault();
                    e.stopPropagation();
                    window.closeUpgradeModal();
                };

                modal._cancelBtnHandler = function(e) {
                    console.log('🔒 Click en Cerrar - cerrando modal');
                    e.preventDefault();
                    e.stopPropagation();
                    window.closeUpgradeModal();
                };

                modal._plansBtnHandler = function(e) {
                    console.log('📋 Click en Ver Planes');
                    e.preventDefault();
                    e.stopPropagation();
                    window.goToPlans();
                };

                modal._contentHandler = function(e) {
                    e.stopPropagation();
                };

                if (overlay) {
                    overlay.addEventListener('click', modal._overlayHandler);
                }

                if (closeBtn) {
                    closeBtn.addEventListener('click', modal._closeBtnHandler);
                }

                if (cancelBtn) {
                    cancelBtn.addEventListener('click', modal._cancelBtnHandler);
                }

                if (plansBtn) {
                    plansBtn.addEventListener('click', modal._plansBtnHandler);
                }

                if (content) {
                    content.addEventListener('click', modal._contentHandler);
                }

                modal._listenersAdded = true;
                console.log('✅ Event listeners agregados al modal');
            };

            window.showUpgradeModal = function(options = {}) {
                console.log('🚀 INICIANDO showUpgradeModal global...');

                const defaults = {
                    title: 'Actualiza tu plan',
                    message: 'Esta funcionalidad requiere un plan premium.',
                    icon: 'lock',
                    hideCloseButton: false
                };

                const config = { ...defaults, ...options };

                // Crear modal dinámicamente
                let modal = document.getElementById('upgrade-modal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'upgrade-modal';
                    modal.className = 'modal';
                    modal.innerHTML = `
                        <div class="modal-overlay" id="modal-overlay-${Date.now()}"></div>
                        <div class="modal-content" id="modal-content-${Date.now()}">
                            <div class="modal-header">
                                <h3 id="modal-title">${config.title}</h3>
                                ${!config.hideCloseButton ? '<button class="modal-close" id="modal-close-btn">&times;</button>' : ''}
                            </div>
                            <div class="modal-body">
                                <div class="modal-icon">
                                    ${config.icon === 'lock' ? '🔒' : '⭐'}
                                </div>
                                <div class="modal-message">
                                    <p id="modal-message">${config.message}</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="modal-plans-btn" class="btn btn-primary">Ver Planes</button>
                                <button type="button" id="modal-cancel-btn" class="btn btn-secondary">Cerrar</button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);

                    // Asegurarse de que los listeners estén presentes en el modal recién creado
                    requestAnimationFrame(() => ensureModalEventListeners(modal));
                } else {
                    // Actualizar contenido del modal existente
                    modal.querySelector('#modal-title').innerHTML = config.title;
                    modal.querySelector('#modal-message').innerHTML = config.message;

                    // Restaurar visibilidad del botón de cierre si se había ocultado
                    const closeBtn = modal.querySelector('#modal-close-btn');
                    if (closeBtn) {
                        closeBtn.style.display = config.hideCloseButton ? 'none' : '';
                    }

                    console.log('🔄 Reutilizando modal existente');
                }

                // Asegurar listeners también para modales reutilizados
                ensureModalEventListeners(modal);

                // Mostrar modal
                modal.style.display = 'flex';
                modal.classList.add('active');

                // Agregar event listener para Escape
                const handleEscape = function(e) {
                    if (e.key === 'Escape') {
                        console.log('🔒 Escape presionado - cerrando modal');
                        window.closeUpgradeModal();
                        document.removeEventListener('keydown', handleEscape);
                    }
                };
                document.addEventListener('keydown', handleEscape);

                // Guardar referencia para poder remover el listener
                modal._escapeHandler = handleEscape;

                console.log('✅ Modal de upgrade mostrado');
            };
        }

        // Funciones auxiliares para el modal
        window.closeUpgradeModal = function() {
            console.log('🔒 Cerrando modal de upgrade...');
            const modal = document.getElementById('upgrade-modal');
            if (modal) {
                // Remover event listener de Escape si existe
                if (modal._escapeHandler) {
                    document.removeEventListener('keydown', modal._escapeHandler);
                    modal._escapeHandler = null;
                }

                // Limpiar event listeners para evitar acumulación
                if (modal._listenersAdded) {
                    const overlay = modal.querySelector('.modal-overlay');
                    const closeBtn = modal.querySelector('#modal-close-btn');
                    const cancelBtn = modal.querySelector('#modal-cancel-btn');
                    const plansBtn = modal.querySelector('#modal-plans-btn');
                    const content = modal.querySelector('.modal-content');

                    if (overlay && modal._overlayHandler) {
                        overlay.removeEventListener('click', modal._overlayHandler);
                    }
                    if (closeBtn && modal._closeBtnHandler) {
                        closeBtn.removeEventListener('click', modal._closeBtnHandler);
                    }
                    if (cancelBtn && modal._cancelBtnHandler) {
                        cancelBtn.removeEventListener('click', modal._cancelBtnHandler);
                    }
                    if (plansBtn && modal._plansBtnHandler) {
                        plansBtn.removeEventListener('click', modal._plansBtnHandler);
                    }
                    if (content && modal._contentHandler) {
                        content.removeEventListener('click', modal._contentHandler);
                    }

                    modal._listenersAdded = false;
                    modal._overlayHandler = null;
                    modal._closeBtnHandler = null;
                    modal._cancelBtnHandler = null;
                    modal._plansBtnHandler = null;
                    modal._contentHandler = null;
                    console.log('🧹 Event listeners removidos');
                }

                // Agregar animación de salida
                modal.style.animation = 'modalSlideOut 0.2s ease-in forwards';

                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.classList.remove('active');
                    modal.style.animation = '';
                    console.log('✅ Modal cerrado correctamente');
                }, 200);
            } else {
                console.warn('⚠️ Modal no encontrado para cerrar');
            }
        };

        window.goToPlans = function() {
            console.log('📋 Redirigiendo a planes...');
            // Primero cerrar el modal
            window.closeUpgradeModal();
            // Luego redirigir
            setTimeout(() => {
                window.location.href = '/profile#plans';
            }, 300);
        };

        // Función para mostrar modal de tareas bloqueadas
        window.showTasksLockedModal = function() {
            console.log('🚫 Mostrando modal de tareas bloqueadas');

            window.showUpgradeModal({
                title: 'Gestión de tareas no disponible',
                message: 'La gestión de tareas está disponible para los planes <strong>Business</strong> y <strong>Enterprise</strong>.<br><br>Actualiza tu plan para acceder a esta funcionalidad.',
                icon: 'lock',
                hideCloseButton: false
            });
        };

        // Función específica para documentos del AI Assistant
        window.showDocumentLimitModal = function() {
            console.log('📄 Mostrando modal de límite de documentos');

            window.showUpgradeModal({
                title: 'Límite de documentos alcanzado',
                message: 'Has alcanzado el límite diario de 1 documento. Los usuarios FREE pueden subir hasta 1 documento por día.<br><br>Actualiza tu plan para tener acceso ilimitado.',
                icon: 'lock',
                hideCloseButton: false
            });
        };

        console.debug('[global-vars partial] userRole=', window.userRole, 'currentOrganizationId=', window.currentOrganizationId, 'userPlanCode=', window.userPlanCode, 'userBelongsToOrganization=', window.userBelongsToOrganization);
    } catch(e) {
        console.error('[global-vars partial] Error injecting globals', e);
    }
})();
</script>

<!-- Estilos para modal global -->
<style>
#upgrade-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

#upgrade-modal .modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

#upgrade-modal .modal-content {
    position: relative;
    background: #1e293b;
    padding: 2rem;
    border-radius: 16px;
    max-width: 480px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    border: 1px solid rgba(59, 130, 246, 0.2);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes modalSlideOut {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
}

#upgrade-modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(59, 130, 246, 0.1);
}

#upgrade-modal .modal-header h3 {
    color: #f1f5f9;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    flex: 1;
    text-align: center;
    padding-right: 2rem;
}

#upgrade-modal .modal-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
    flex-shrink: 0;
}

#upgrade-modal .modal-close:hover {
    background: rgba(148, 163, 184, 0.1);
    color: #f1f5f9;
}

#upgrade-modal .modal-body {
    text-align: center;
    margin-bottom: 2rem;
}

#upgrade-modal .modal-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: 4rem;
    height: 80px;
}

#upgrade-modal .modal-message {
    margin-top: 1rem;
}

#upgrade-modal .modal-message p {
    color: #cbd5e1;
    line-height: 1.7;
    margin: 0 0 1rem 0;
    font-size: 1rem;
}

#upgrade-modal .modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

#upgrade-modal .btn {
    padding: 0.875rem 2rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    border: none;
    font-size: 0.95rem;
    min-width: 120px;
}

#upgrade-modal .btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

#upgrade-modal .btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-2px);
}

#upgrade-modal .btn-secondary {
    background: rgba(148, 163, 184, 0.1);
    color: #cbd5e1;
    border: 1px solid rgba(148, 163, 184, 0.3);
}

#upgrade-modal .btn-secondary:hover {
    background: rgba(148, 163, 184, 0.2);
    color: #f1f5f9;
    border-color: rgba(148, 163, 184, 0.5);
}

/* Responsive */
@media (max-width: 640px) {
    #upgrade-modal .modal-content {
        margin: 1rem;
        padding: 1.5rem;
        width: calc(100% - 2rem);
    }

    #upgrade-modal .modal-header h3 {
        font-size: 1.25rem;
        padding-right: 1rem;
    }

    #upgrade-modal .modal-footer {
        flex-direction: column;
    }

    #upgrade-modal .btn {
        width: 100%;
    }
}
</style>

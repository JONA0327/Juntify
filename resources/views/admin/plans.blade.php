<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administrar Planes - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/js/profile.js',
        'resources/js/admin-plans.js'
    ])
</head>
<body class="admin-panels-page">
    <div class="particles" id="particles"></div>

    @include('partials.navbar')

    <div class="mobile-bottom-nav">
        <div class="nav-item" onclick="window.location.href='{{ route('dashboard') }}'">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            <span class="nav-label">Inicio</span>
        </div>
        <div class="nav-item" onclick="window.location.href='{{ route('admin.dashboard') }}'">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="nav-label">Admin</span>
        </div>
    </div>

    <div class="app-container">
        <main class="main-admin">
            <div class="content-header">
                <div>
                    <h1 class="page-title">Administrar planes</h1>
                    <p class="page-subtitle">Actualiza precios, descuentos y vigencia para los planes Free, Basic, Business y Enterprise.</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='{{ route('admin.dashboard') }}'">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver al panel
                    </button>
                </div>
            </div>

            <div id="admin-plans-alert" class="hidden"></div>

            <div class="w-full">
                <div class="info-card" style="padding: 0; overflow: hidden;">
                    <div class="card-header" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                        <div>
                            <h2 class="card-title" style="margin-bottom: 0.5rem;">Planes configurados</h2>
                            <p class="card-subtitle" style="margin: 0;">Precios mensuales y anuales, con descuentos y meses gratis.</p>
                        </div>
                        <button class="action-btn create" onclick="openPlanModal()" style="margin-left: auto;">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Crear Plan
                        </button>
                    </div>
                    <div class="overflow-x-auto" style="padding: 0;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 140px;">Plan</th>
                                    <th style="text-align: right; min-width: 100px;">Mensual</th>
                                    <th style="text-align: right; min-width: 100px;">Anual</th>
                                    <th style="text-align: center; min-width: 100px;">Descuento</th>
                                    <th style="text-align: center; min-width: 110px;">Meses gratis</th>
                                    <th style="text-align: center; min-width: 90px;">Estado</th>
                                    <th style="min-width: 140px;">Actualizado</th>
                                    <th style="text-align: center; min-width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="plans-table-body">
                                <tr>
                                    <td colspan="8" class="text-center py-8 text-slate-400" style="font-style: italic;">
                                        No hay planes configurados todavía
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
        </main>
    </div>

    <!-- Modal para Crear/Editar Plan -->
    <div id="planModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 id="modalTitle" class="modal-title">Crear Plan</h3>
                <button class="modal-close" onclick="closePlanModal()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="planForm" class="modal-content">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="planCode">Código del plan</label>
                        <select id="planCode" name="code" required>
                            <option value="">Selecciona el plan</option>
                            <option value="free">Free</option>
                            <option value="basic">Basic</option>
                            <option value="business">Business</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="planName">Nombre del plan</label>
                        <input type="text" id="planName" name="name" required placeholder="Ej: Free">
                    </div>
                </div>

                <div class="form-group">
                    <label for="planDescription">Descripción</label>
                    <textarea id="planDescription" name="description" placeholder="Gratis para siempre" rows="3"></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="monthlyPrice">Precio mensual</label>
                        <input type="number" id="monthlyPrice" name="monthly_price" step="0.01" min="0" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="yearlyPrice">Precio anual</label>
                        <input type="number" id="yearlyPrice" name="yearly_price" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="discountPercentage">% descuento (opcional)</label>
                        <input type="number" id="discountPercentage" name="discount_percentage" min="0" max="100" step="0.01" placeholder="0">
                    </div>

                    <div class="form-group">
                        <label for="freeMonths">Meses gratis (planes de pago)</label>
                        <input type="number" id="freeMonths" name="free_months" min="0" max="12" placeholder="0">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="currency">Moneda</label>
                        <select id="currency" name="currency" required>
                            <option value="MXN">MXN</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="isEnabled" name="enabled" checked>
                            <span class="checkbox-text">Habilitar plan</span>
                        </label>
                    </div>
                </div>
            </form>

            <div class="modal-footer">
                <button type="button" class="action-btn secondary" onclick="closePlanModal()">Cancelar</button>
                <button type="button" class="action-btn create" onclick="savePlan()">Guardar Plan</button>
            </div>
        </div>
    </div>

    <style>
        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.3;
        }
        .card-subtitle {
            color: #cbd5e1;
            opacity: 0.9;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .toggle-checkbox {
            width: 48px;
            height: 24px;
            accent-color: #3b82f6;
        }

        /* Mejoras específicas para la tabla de planes */
        .admin-table tbody tr td:nth-child(1) {
            font-weight: 600;
            color: #60a5fa;
        }

        .admin-table tbody tr td:nth-child(2),
        .admin-table tbody tr td:nth-child(3) {
            color: #34d399;
            font-weight: 500;
        }

        .admin-table tbody tr td:nth-child(4),
        .admin-table tbody tr td:nth-child(5) {
            color: #fbbf24;
            font-weight: 500;
        }

        .admin-table tbody tr td:nth-child(6) .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-table tbody tr td:nth-child(6) .status-active {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .admin-table tbody tr td:nth-child(6) .status-inactive {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .overflow-x-auto {
            background: rgba(15, 23, 42, 0.3);
            border-radius: 0 0 0.75rem 0.75rem;
        }

        /* Estilos para botones de acción */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            align-items: center;
        }

        .action-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .action-btn.edit {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .action-btn.edit:hover {
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }

        .action-btn.delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .action-btn.delete:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .action-btn svg {
            width: 14px;
            height: 14px;
        }

        .action-btn.create {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .action-btn.create:hover {
            background: linear-gradient(135deg, #059669, #047857);
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .action-btn.secondary:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
        }

        /* Estilos del Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-container {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 1rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(59, 130, 246, 0.2);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
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

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(59, 130, 246, 0.2);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .modal-close svg {
            width: 20px;
            height: 20px;
        }

        .modal-content {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(59, 130, 246, 0.2);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Estilos del formulario */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.5rem;
            background: rgba(15, 23, 42, 0.5);
            color: #f1f5f9;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding-top: 0.5rem;
        }

        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox-text {
            font-weight: 500;
            color: #cbd5e1;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-container {
                margin: 1rem;
            }
        }
    </style>

    <script>
        let currentEditingPlan = null;

        // Función para cargar y mostrar los planes
        function loadPlans() {
            const tableBody = document.getElementById('plans-table-body');

            // Simulamos datos de ejemplo - aquí deberías hacer una petición AJAX real
            const samplePlans = [
                {
                    id: 1,
                    code: 'free',
                    name: 'Free',
                    description: 'Gratis para siempre',
                    monthly_price: 350,
                    yearly_price: 999,
                    discount_percentage: 0,
                    free_months: 0,
                    currency: 'MXN',
                    enabled: false,
                    updated_at: '25/11/25, 5:11 p.m.'
                }
                // Aquí puedes agregar más planes de ejemplo o cargar desde API
            ];

            if (samplePlans.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-8 text-slate-400" style="font-style: italic;">
                            No hay planes configurados todavía
                        </td>
                    </tr>
                `;
                return;
            }

            tableBody.innerHTML = samplePlans.map(plan => {
                const currencySymbol = plan.currency === 'USD' ? '$' : plan.currency === 'EUR' ? '€' : '$';
                const monthlyDisplay = plan.monthly_price > 0 ? `${currencySymbol}${plan.monthly_price}` : 'Gratis';
                const yearlyDisplay = plan.yearly_price > 0 ? `${currencySymbol}${plan.yearly_price}` : 'Gratis';
                const discountDisplay = plan.discount_percentage > 0 ? `${plan.discount_percentage}%` : '—';
                const freeMonthsDisplay = plan.free_months > 0 ? `${plan.free_months}` : '—';
                const statusText = plan.enabled ? 'Activo' : 'Deshabilitado';
                const statusClass = plan.enabled ? 'status-active' : 'status-inactive';

                return `
                    <tr>
                        <td style="color: #60a5fa; font-weight: 600;">${plan.name}</td>
                        <td style="text-align: right; color: #34d399; font-weight: 500;">${monthlyDisplay}</td>
                        <td style="text-align: right; color: #34d399; font-weight: 500;">${yearlyDisplay}</td>
                        <td style="text-align: center; color: #fbbf24; font-weight: 500;">${discountDisplay}</td>
                        <td style="text-align: center; color: #fbbf24; font-weight: 500;">${freeMonthsDisplay}</td>
                        <td style="text-align: center;">
                            <span class="status-badge ${statusClass}">
                                ${statusText}
                            </span>
                        </td>
                        <td style="color: #94a3b8; font-size: 0.8rem;">${plan.updated_at}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editPlan(${plan.id})">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Editar
                                </button>
                                <button class="action-btn delete" onclick="deletePlan(${plan.id}, '${plan.name}')">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Función para abrir el modal (crear nuevo plan)
        function openPlanModal() {
            currentEditingPlan = null;
            document.getElementById('modalTitle').textContent = 'Crear Plan';
            document.getElementById('planForm').reset();
            document.getElementById('planModal').style.display = 'flex';

            // Enfocar el primer campo
            setTimeout(() => {
                document.getElementById('planCode').focus();
            }, 100);
        }

        // Función para editar un plan existente
        function editPlan(planId) {
            console.log('Editando plan ID:', planId);

            // Simular datos del plan (normalmente harías una petición AJAX)
            const planData = {
                id: 1,
                code: 'free',
                name: 'Free',
                description: 'Gratis para siempre',
                monthly_price: 350,
                yearly_price: 999,
                discount_percentage: 0,
                free_months: 0,
                currency: 'MXN',
                enabled: false
            };

            currentEditingPlan = planId;
            document.getElementById('modalTitle').textContent = 'Editar Plan';

            // Pre-rellenar el formulario
            document.getElementById('planCode').value = planData.code;
            document.getElementById('planName').value = planData.name;
            document.getElementById('planDescription').value = planData.description;
            document.getElementById('monthlyPrice').value = planData.monthly_price;
            document.getElementById('yearlyPrice').value = planData.yearly_price;
            document.getElementById('discountPercentage').value = planData.discount_percentage;
            document.getElementById('freeMonths').value = planData.free_months;
            document.getElementById('currency').value = planData.currency;
            document.getElementById('isEnabled').checked = planData.enabled;

            // Mostrar el modal
            document.getElementById('planModal').style.display = 'flex';
        }

        // Función para cerrar el modal
        function closePlanModal() {
            document.getElementById('planModal').style.display = 'none';
            currentEditingPlan = null;
        }

        // Función para guardar el plan
        function savePlan() {
            const formData = {
                code: document.getElementById('planCode').value,
                name: document.getElementById('planName').value,
                description: document.getElementById('planDescription').value,
                monthly_price: parseFloat(document.getElementById('monthlyPrice').value) || 0,
                yearly_price: parseFloat(document.getElementById('yearlyPrice').value) || 0,
                discount_percentage: parseFloat(document.getElementById('discountPercentage').value) || 0,
                free_months: parseInt(document.getElementById('freeMonths').value) || 0,
                currency: document.getElementById('currency').value,
                enabled: document.getElementById('isEnabled').checked
            };

            // Validación básica
            if (!formData.code || !formData.name || !formData.currency) {
                alert('Por favor completa los campos obligatorios: Código, Nombre y Moneda.');
                return;
            }

            console.log('Guardando plan:', formData);

            // Aquí harías la petición AJAX para guardar
            const method = currentEditingPlan ? 'PUT' : 'POST';
            const url = currentEditingPlan ? `/admin/plans/${currentEditingPlan}` : '/admin/plans';

            // Ejemplo de petición AJAX:
            // fetch(url, {
            //     method: method,
            //     headers: {
            //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            //         'Content-Type': 'application/json'
            //     },
            //     body: JSON.stringify(formData)
            // })
            // .then(response => response.json())
            // .then(data => {
            //     if (data.success) {
            //         closePlanModal();
            //         loadPlans(); // Recargar la tabla
            //         alert(currentEditingPlan ? 'Plan actualizado exitosamente.' : 'Plan creado exitosamente.');
            //     } else {
            //         alert('Error: ' + (data.message || 'No se pudo guardar el plan.'));
            //     }
            // })
            // .catch(error => {
            //     console.error('Error:', error);
            //     alert('Error de conexión. Inténtalo de nuevo.');
            // });

            // Por ahora, solo simulamos el guardado
            const action = currentEditingPlan ? 'actualizado' : 'creado';
            alert(`Plan "${formData.name}" ${action} exitosamente.`);
            closePlanModal();
            loadPlans(); // Recargar la tabla
        }

        // Función para eliminar un plan
        function deletePlan(planId, planName) {
            console.log('Eliminando plan ID:', planId);

            // Confirmación antes de eliminar
            if (confirm(`¿Estás seguro de que quieres eliminar el plan "${planName}"?\n\nEsta acción no se puede deshacer.`)) {
                // Aquí harías la petición AJAX para eliminar el plan
                console.log(`Plan ${planId} eliminado`);

                // Ejemplo de petición AJAX:
                // fetch(`/admin/plans/${planId}`, {
                //     method: 'DELETE',
                //     headers: {
                //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                //         'Content-Type': 'application/json'
                //     }
                // })
                // .then(response => response.json())
                // .then(data => {
                //     if (data.success) {
                //         loadPlans(); // Recargar la tabla
                //         alert(`Plan "${planName}" eliminado exitosamente.`);
                //     } else {
                //         alert('Error: ' + (data.message || 'No se pudo eliminar el plan.'));
                //     }
                // });

                // Por ahora, solo mostramos un mensaje
                alert(`Plan "${planName}" eliminado exitosamente.`);
                loadPlans(); // Recargar la tabla
            }
        }

        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('planModal').style.display === 'flex') {
                closePlanModal();
            }
        });

        // Cerrar modal al hacer clic fuera
        document.getElementById('planModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePlanModal();
            }
        });

        // Cargar planes cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            loadPlans();
        });
    </script>

</body>
</html>

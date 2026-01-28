const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const alertBox = document.getElementById('admin-plans-alert');
const planForm = document.getElementById('planForm');
const planTemplates = planForm ? JSON.parse(planForm.dataset.planTemplates || '{}') : {};
const planCodeSelect = document.getElementById('planCode');
const planNameInput = document.getElementById('planName');
const planDescriptionInput = document.getElementById('planDescription');
const monthlyPriceInput = document.getElementById('monthlyPrice');
const yearlyPriceInput = document.getElementById('yearlyPrice');
const discountInput = document.getElementById('discountPercentage');
const freeMonthsInput = document.getElementById('freeMonths');
const currencyInput = document.getElementById('currency');
const isActiveInput = document.getElementById('isEnabled');
const plansTableBody = document.getElementById('plans-table-body');
const planLimitModal = document.getElementById('planLimitModal');
const planLimitForm = document.getElementById('planLimitForm');
const planLimitsTableBody = document.getElementById('plan-limits-table-body');
const limitRoleInput = document.getElementById('limitRole');
const maxMeetingsPerMonthInput = document.getElementById('maxMeetingsPerMonth');
const maxDurationMinutesInput = document.getElementById('maxDurationMinutes');
const warnBeforeMinutesInput = document.getElementById('warnBeforeMinutes');
const allowPostponeInput = document.getElementById('allowPostpone');
const taskViewCalendarInput = document.getElementById('taskViewCalendar');
const taskViewBoardInput = document.getElementById('taskViewBoard');
const maxContainersPersonalInput = document.getElementById('maxContainersPersonal');
const maxMeetingsPerContainerPersonalInput = document.getElementById('maxMeetingsPerContainerPersonal');
const maxContainersOrgInput = document.getElementById('maxContainersOrg');
const maxMeetingsPerContainerOrgInput = document.getElementById('maxMeetingsPerContainerOrg');

const showAlert = (type, message) => {
    if (!alertBox) return;

    alertBox.className = '';
    alertBox.classList.add('info-card', 'mb-4');
    alertBox.style.display = 'block';
    alertBox.textContent = message;

    if (type === 'success') {
        alertBox.classList.add('bg-green-500/10', 'text-green-200', 'border', 'border-green-400/40');
    } else {
        alertBox.classList.add('bg-red-500/10', 'text-red-200', 'border', 'border-red-400/40');
    }

    setTimeout(() => {
        alertBox.classList.add('hidden');
        alertBox.style.display = 'none';
    }, 4500);
};

const hideAlert = () => {
    if (!alertBox) return;
    alertBox.className = 'hidden';
    alertBox.textContent = '';
    alertBox.style.display = 'none';
};

const formatMoney = (amount, currency = 'MXN') => {
    const numeric = Number(amount) || 0;
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric);
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
};

const renderPlans = (plans = []) => {
    if (!plansTableBody) return;

    if (!plans.length) {
        plansTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-6 text-slate-400">No hay planes configurados todavía</td>
            </tr>
        `;
        return;
    }

    const fragment = document.createDocumentFragment();

    plans.forEach((plan) => {
        const row = document.createElement('tr');
        row.dataset.planId = plan.id;

        const planCell = document.createElement('td');
        planCell.className = 'px-4 py-3 text-slate-200 font-semibold';
        planCell.textContent = `${plan.name} (${plan.code})`;

        const monthlyCell = document.createElement('td');
        monthlyCell.className = 'px-4 py-3 text-slate-300';
        monthlyCell.textContent = formatMoney(plan.monthly_price, plan.currency);

        const yearlyCell = document.createElement('td');
        yearlyCell.className = 'px-4 py-3 text-slate-300';
        const yearlyText = plan.discount_percentage > 0 || plan.free_months > 0
            ? `${formatMoney(plan.yearly_price, plan.currency)} (${formatMoney(plan.yearly_base_price, plan.currency)} base)`
            : formatMoney(plan.yearly_price, plan.currency);
        yearlyCell.textContent = yearlyText;

        const discountCell = document.createElement('td');
        discountCell.className = 'px-4 py-3 text-slate-300';
        discountCell.textContent = plan.discount_percentage ? `${plan.discount_percentage}%` : '—';

        const freeMonthsCell = document.createElement('td');
        freeMonthsCell.className = 'px-4 py-3 text-slate-300';
        freeMonthsCell.textContent = plan.free_months ? `${plan.free_months} mes(es)` : '—';

        const statusCell = document.createElement('td');
        statusCell.className = 'px-4 py-3';
        const statusBadge = document.createElement('span');
        statusBadge.className = plan.is_active
            ? 'bg-green-500/20 text-green-200 px-2 py-1 rounded-full text-xs'
            : 'bg-slate-600/40 text-slate-200 px-2 py-1 rounded-full text-xs';
        statusBadge.textContent = plan.is_active ? 'Habilitado' : 'Deshabilitado';
        statusCell.appendChild(statusBadge);

        const updatedCell = document.createElement('td');
        updatedCell.className = 'px-4 py-3 text-slate-300';
        updatedCell.textContent = formatDateTime(plan.updated_at);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-4 py-3 text-center';
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'action-btn edit';
        editButton.innerHTML = `
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M4 13v7h7l9.878-9.878a2.5 2.5 0 00-3.536-3.536L7.464 16.464H4z"></path>
            </svg>
            Editar
        `;
        editButton.addEventListener('click', () => openPlanModal(plan));
        actionsCell.appendChild(editButton);

        row.appendChild(planCell);
        row.appendChild(monthlyCell);
        row.appendChild(yearlyCell);
        row.appendChild(discountCell);
        row.appendChild(freeMonthsCell);
        row.appendChild(statusCell);
        row.appendChild(updatedCell);
        row.appendChild(actionsCell);

        fragment.appendChild(row);
    });

    plansTableBody.innerHTML = '';
    plansTableBody.appendChild(fragment);
};

const fetchPlans = async () => {
    try {
        const response = await fetch('/admin/plans/list', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('No se pudieron cargar los planes');
        const plans = await response.json();
        renderPlans(plans);
    } catch (error) {
        showAlert('error', error.message || 'Ocurrió un error al cargar los planes');
    }
};

const fillTemplateDefaults = (code) => {
    const template = planTemplates[code];
    if (!template) return;

    planNameInput.value = template.default_name || '';
    planDescriptionInput.value = template.description || '';
    monthlyPriceInput.value = '';
    yearlyPriceInput.value = '';
    discountInput.value = '';
    freeMonthsInput.value = '';
};

const serializeForm = () => {
    return {
        plan_code: planCodeSelect.value,
        name: planNameInput.value,
        description: planDescriptionInput.value,
        currency: currencyInput.value || 'MXN',
        monthly_price: monthlyPriceInput.value,
        yearly_price: yearlyPriceInput.value || null,
        discount_percentage: discountInput.value || null,
        free_months: freeMonthsInput.value || null,
        is_active: isActiveInput.checked ? 1 : 0,
    };
};

const openPlanModal = (plan = null) => {
    if (!planForm) return;
    hideAlert();
    planForm.reset();
    if (plan) {
        planCodeSelect.value = plan.code || '';
        planNameInput.value = plan.name || '';
        planDescriptionInput.value = plan.description || '';
        monthlyPriceInput.value = plan.monthly_price ?? '';
        yearlyPriceInput.value = plan.yearly_price ?? '';
        discountInput.value = plan.discount_percentage ?? '';
        freeMonthsInput.value = plan.free_months ?? '';
        currencyInput.value = plan.currency || 'MXN';
        isActiveInput.checked = !!plan.is_active;
    } else if (planCodeSelect.value) {
        fillTemplateDefaults(planCodeSelect.value);
    }

    const modal = document.getElementById('planModal');
    if (modal) modal.style.display = 'flex';
};

const closePlanModal = () => {
    const modal = document.getElementById('planModal');
    if (modal) modal.style.display = 'none';
};

const savePlan = () => {
    if (!planForm) return;
    planForm.requestSubmit();
};

const handleSubmit = async (event) => {
    event.preventDefault();
    hideAlert();

    const payload = serializeForm();

    try {
        const response = await fetch('/admin/plans', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = data.message || 'No se pudo guardar el plan';
            throw new Error(message);
        }

        const data = await response.json();
        showAlert('success', 'Plan guardado correctamente');
        if (data.plan) {
            fetchPlans();
            closePlanModal();
        }
    } catch (error) {
        showAlert('error', error.message || 'Error desconocido');
    }
};

const formatLimitValue = (value) => {
    if (value === null || typeof value === 'undefined') return '∞';
    return Number.isFinite(Number(value)) ? value : '—';
};

const formatTaskViews = (views = []) => {
    if (!Array.isArray(views) || !views.length) return '—';
    return views
        .map((view) => (view === 'tablero' ? 'Tablero' : 'Calendario'))
        .join(', ');
};

const renderPlanLimits = (limits = []) => {
    if (!planLimitsTableBody) return;

    if (!limits.length) {
        planLimitsTableBody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center py-6 text-slate-400">No hay límites configurados todavía</td>
            </tr>
        `;
        return;
    }

    const fragment = document.createDocumentFragment();

    limits.forEach((limit) => {
        const row = document.createElement('tr');
        row.dataset.limitRole = limit.role;

        const roleCell = document.createElement('td');
        roleCell.className = 'px-4 py-3 text-slate-200 font-semibold';
        roleCell.textContent = limit.role;

        const meetingsCell = document.createElement('td');
        meetingsCell.className = 'px-4 py-3 text-center text-slate-300';
        meetingsCell.textContent = formatLimitValue(limit.max_meetings_per_month);

        const durationCell = document.createElement('td');
        durationCell.className = 'px-4 py-3 text-center text-slate-300';
        durationCell.textContent = formatLimitValue(limit.max_duration_minutes);

        const warnCell = document.createElement('td');
        warnCell.className = 'px-4 py-3 text-center text-slate-300';
        warnCell.textContent = formatLimitValue(limit.warn_before_minutes);

        const postponeCell = document.createElement('td');
        postponeCell.className = 'px-4 py-3 text-center text-slate-300';
        postponeCell.textContent = limit.allow_postpone ? 'Sí' : 'No';

        const taskViewsCell = document.createElement('td');
        taskViewsCell.className = 'px-4 py-3 text-center text-slate-300';
        taskViewsCell.textContent = formatTaskViews(limit.task_views);

        const containersPersonalCell = document.createElement('td');
        containersPersonalCell.className = 'px-4 py-3 text-center text-slate-300';
        containersPersonalCell.textContent = formatLimitValue(limit.max_containers_personal);

        const meetingsPerContainerPersonalCell = document.createElement('td');
        meetingsPerContainerPersonalCell.className = 'px-4 py-3 text-center text-slate-300';
        meetingsPerContainerPersonalCell.textContent = formatLimitValue(limit.max_meetings_per_container_personal);

        const containersOrgCell = document.createElement('td');
        containersOrgCell.className = 'px-4 py-3 text-center text-slate-300';
        containersOrgCell.textContent = formatLimitValue(limit.max_containers_org);

        const meetingsPerContainerOrgCell = document.createElement('td');
        meetingsPerContainerOrgCell.className = 'px-4 py-3 text-center text-slate-300';
        meetingsPerContainerOrgCell.textContent = formatLimitValue(limit.max_meetings_per_container_org);

        const updatedCell = document.createElement('td');
        updatedCell.className = 'px-4 py-3 text-slate-300';
        updatedCell.textContent = formatDateTime(limit.updated_at);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-4 py-3 text-center';
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'action-btn edit';
        editButton.innerHTML = `
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M4 13v7h7l9.878-9.878a2.5 2.5 0 00-3.536-3.536L7.464 16.464H4z"></path>
            </svg>
            Editar
        `;
        editButton.addEventListener('click', () => openPlanLimitModal(limit));
        actionsCell.appendChild(editButton);

        row.appendChild(roleCell);
        row.appendChild(meetingsCell);
        row.appendChild(durationCell);
        row.appendChild(warnCell);
        row.appendChild(postponeCell);
        row.appendChild(taskViewsCell);
        row.appendChild(containersPersonalCell);
        row.appendChild(meetingsPerContainerPersonalCell);
        row.appendChild(containersOrgCell);
        row.appendChild(meetingsPerContainerOrgCell);
        row.appendChild(updatedCell);
        row.appendChild(actionsCell);

        fragment.appendChild(row);
    });

    planLimitsTableBody.innerHTML = '';
    planLimitsTableBody.appendChild(fragment);
};

const fetchPlanLimits = async () => {
    try {
        const response = await fetch('/admin/plans/limits', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('No se pudieron cargar los límites');
        const limits = await response.json();
        renderPlanLimits(limits);
    } catch (error) {
        showAlert('error', error.message || 'Ocurrió un error al cargar los límites');
    }
};

const openPlanLimitModal = (limit = null) => {
    if (!planLimitForm || !planLimitModal) return;
    hideAlert();
    planLimitForm.reset();
    const viewDefaults = ['calendario', 'tablero'];
    if (limit) {
        limitRoleInput.value = limit.role || '';
        maxMeetingsPerMonthInput.value = limit.max_meetings_per_month ?? '';
        maxDurationMinutesInput.value = limit.max_duration_minutes ?? '';
        warnBeforeMinutesInput.value = limit.warn_before_minutes ?? '';
        allowPostponeInput.checked = !!limit.allow_postpone;
        const taskViews = Array.isArray(limit.task_views) && limit.task_views.length
            ? limit.task_views
            : viewDefaults;
        if (taskViewCalendarInput) taskViewCalendarInput.checked = taskViews.includes('calendario');
        if (taskViewBoardInput) taskViewBoardInput.checked = taskViews.includes('tablero');
        maxContainersPersonalInput.value = limit.max_containers_personal ?? '';
        maxMeetingsPerContainerPersonalInput.value = limit.max_meetings_per_container_personal ?? '';
        maxContainersOrgInput.value = limit.max_containers_org ?? '';
        maxMeetingsPerContainerOrgInput.value = limit.max_meetings_per_container_org ?? '';
    } else {
        if (taskViewCalendarInput) taskViewCalendarInput.checked = true;
        if (taskViewBoardInput) taskViewBoardInput.checked = true;
    }

    planLimitModal.style.display = 'flex';
};

const closePlanLimitModal = () => {
    if (planLimitModal) planLimitModal.style.display = 'none';
};

const parseOptionalNumber = (value) => {
    if (value === '' || value === null || typeof value === 'undefined') return null;
    const numeric = Number(value);
    return Number.isNaN(numeric) ? null : numeric;
};

const collectTaskViews = () => {
    const views = [];
    if (taskViewCalendarInput?.checked) views.push('calendario');
    if (taskViewBoardInput?.checked) views.push('tablero');
    return views;
};

const serializeLimitForm = () => ({
    role: limitRoleInput.value.trim(),
    max_meetings_per_month: parseOptionalNumber(maxMeetingsPerMonthInput.value),
    max_duration_minutes: parseOptionalNumber(maxDurationMinutesInput.value),
    warn_before_minutes: parseOptionalNumber(warnBeforeMinutesInput.value),
    allow_postpone: allowPostponeInput.checked ? 1 : 0,
    task_views: collectTaskViews(),
    max_containers_personal: parseOptionalNumber(maxContainersPersonalInput.value),
    max_meetings_per_container_personal: parseOptionalNumber(maxMeetingsPerContainerPersonalInput.value),
    max_containers_org: parseOptionalNumber(maxContainersOrgInput.value),
    max_meetings_per_container_org: parseOptionalNumber(maxMeetingsPerContainerOrgInput.value),
});

const savePlanLimit = async () => {
    if (!planLimitForm) return;
    hideAlert();

    const payload = serializeLimitForm();

    try {
        const response = await fetch('/admin/plans/limits', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = data.message || 'No se pudieron guardar los límites';
            throw new Error(message);
        }

        await response.json();
        showAlert('success', 'Límites actualizados correctamente');
        fetchPlanLimits();
        closePlanLimitModal();
    } catch (error) {
        showAlert('error', error.message || 'Error desconocido');
    }
};

if (planCodeSelect) {
    planCodeSelect.addEventListener('change', (event) => fillTemplateDefaults(event.target.value));
}

if (planForm) {
    planForm.addEventListener('submit', handleSubmit);
}

if (planLimitForm) {
    planLimitForm.addEventListener('submit', (event) => {
        event.preventDefault();
        savePlanLimit();
    });
}

fetchPlans();
fetchPlanLimits();

window.openPlanModal = openPlanModal;
window.closePlanModal = closePlanModal;
window.savePlan = savePlan;
window.openPlanLimitModal = openPlanLimitModal;
window.closePlanLimitModal = closePlanLimitModal;
window.savePlanLimit = savePlanLimit;

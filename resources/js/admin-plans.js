const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const alertBox = document.getElementById('admin-plans-alert');
const planForm = document.getElementById('plan-form');
const planTemplates = planForm ? JSON.parse(planForm.dataset.planTemplates || '{}') : {};
const planCodeSelect = document.getElementById('plan-code');
const planNameInput = document.getElementById('plan-name');
const planDescriptionInput = document.getElementById('plan-description');
const monthlyPriceInput = document.getElementById('monthly-price');
const yearlyPriceInput = document.getElementById('yearly-price');
const discountInput = document.getElementById('discount-percentage');
const freeMonthsInput = document.getElementById('free-months');
const currencyInput = document.getElementById('currency');
const isActiveInput = document.getElementById('is-active');
const plansTableBody = document.getElementById('plans-table-body');

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
                <td colspan="7" class="text-center py-6 text-slate-400">No hay planes configurados todavía</td>
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

        row.appendChild(planCell);
        row.appendChild(monthlyCell);
        row.appendChild(yearlyCell);
        row.appendChild(discountCell);
        row.appendChild(freeMonthsCell);
        row.appendChild(statusCell);
        row.appendChild(updatedCell);

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
        }
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

fetchPlans();

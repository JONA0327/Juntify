const PLAN_PAGE_ID = 'subscription-plans-page';

const toggleBilling = () => {
    const monthlyBtn = document.getElementById('monthly-btn');
    const annualBtn = document.getElementById('annual-btn');
    const monthlyPrices = document.querySelectorAll('.monthly-price');
    const annualPrices = document.querySelectorAll('.annual-price');

    if (!monthlyBtn || !annualBtn) return;

    let isAnnual = false;

    monthlyBtn.addEventListener('click', () => {
        if (!isAnnual) return;

        isAnnual = false;
        monthlyBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-white bg-blue-600';
        annualBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-blue-200 hover:text-white';

        monthlyPrices.forEach(el => el.classList.remove('hidden'));
        annualPrices.forEach(el => el.classList.add('hidden'));
    });

    annualBtn.addEventListener('click', () => {
        if (isAnnual) return;

        isAnnual = true;
        annualBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-white bg-blue-600';
        monthlyBtn.className = 'px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-blue-200 hover:text-white';

        monthlyPrices.forEach(el => el.classList.add('hidden'));
        annualPrices.forEach(el => el.classList.remove('hidden'));
    });
};

const getPageConfig = () => {
    const page = document.getElementById(PLAN_PAGE_ID);
    if (!page) return null;

    return {
        preferenceRoute: page.dataset.preferenceRoute,
        csrfToken: page.dataset.csrfToken,
        mpPublicKey: page.dataset.mpPublicKey
    };
};

const showLoading = () => {
    const loadingModal = document.getElementById('loadingModal');
    if (!loadingModal) return;

    loadingModal.classList.remove('hidden');
    loadingModal.classList.add('flex');
};

const hideLoading = () => {
    const loadingModal = document.getElementById('loadingModal');
    if (!loadingModal) return;

    loadingModal.classList.add('hidden');
    loadingModal.classList.remove('flex');
};

const initMercadoPago = (publicKey) => {
    if (!publicKey || typeof MercadoPago === 'undefined') {
        console.error('MercadoPago SDK no disponible o clave pública faltante.');
        return null;
    }

    return new MercadoPago(publicKey);
};

const attachPlanActions = (config) => {
    const mp = initMercadoPago(config.mpPublicKey);

    window.selectPlan = (planId) => {
        if (!mp) return;

        showLoading();

        fetch(config.preferenceRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken
            },
            body: JSON.stringify({ plan_id: planId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.init_point;
                } else {
                    alert('Error: ' + data.error);
                    hideLoading();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar el pago');
                hideLoading();
            });
    };

    window.contactSales = () => {
        alert('Por favor contacta a nuestro equipo de ventas para el plan Empresas');
    };

    window.closePlanExpiredModal = () => {
        const planExpiredModal = document.getElementById('planExpiredModal');
        if (planExpiredModal) {
            planExpiredModal.style.display = 'none';
        }
    };
};

const initializePlansPage = () => {
    const config = getPageConfig();
    if (!config) return;

    toggleBilling();
    attachPlanActions(config);
};

document.addEventListener('DOMContentLoaded', initializePlansPage);

(function() {
    const body = document.body;
    const dataset = body.dataset || {};

    const setIfUndefined = (key, value) => {
        if (typeof window[key] === 'undefined' || window[key] === null || window[key] === '') {
            window[key] = value;
        }
    };

    setIfUndefined('userRole', dataset.userRole || null);
    setIfUndefined('currentOrganizationId', dataset.organizationId || null);
    const showChat = (dataset.contactsShowChat || '').toString() === 'true';
    window.contactsFeatures = Object.assign(window.contactsFeatures || {}, { showChat });

    setIfUndefined('userPlanCode', dataset.userPlanCode || 'free');
    setIfUndefined('userId', dataset.userId || null);
    setIfUndefined('userName', dataset.userName || null);

    function clearContainerErrors() {
        const e1 = document.getElementById('error-name');
        const e2 = document.getElementById('error-description');
        if (e1) { e1.textContent = ''; e1.classList.add('hidden'); }
        if (e2) { e2.textContent = ''; e2.classList.add('hidden'); }
    }

    function updateCharacterCount() {
        const ta = document.getElementById('container-description');
        const counter = document.getElementById('description-count');
        if (ta && counter) counter.textContent = `${ta.value.length}/200`;
    }

    function closeModalLocal() {
        const modal = document.getElementById('container-modal');
        if (modal) modal.classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const cancelDup = document.getElementById('cancel-modal-btn-duplicate');
        const cancelMain = document.getElementById('cancel-modal-btn');
        if (cancelDup) cancelDup.addEventListener('click', closeModalLocal);
        if (cancelMain) cancelMain.addEventListener('click', closeModalLocal);
        updateCharacterCount();
    });

    window.clearContainerErrors = clearContainerErrors;
    window.updateCharacterCount = updateCharacterCount;

    if (typeof window.closeUpgradeModal === 'undefined') {
        window.closeUpgradeModal = function() {
            const possibleIds = ['postpone-locked-modal', 'upgrade-modal', 'container-modal'];
            let modal = null;

            for (const id of possibleIds) {
                modal = document.getElementById(id);
                if (modal) {
                    break;
                }
            }

            if (!modal) {
                modal = document.querySelector('.modal[style*="display: flex"], .modal.show, .modal.active');
            }

            if (modal) {
                modal.style.setProperty('display', 'none', 'important');
                modal.style.setProperty('visibility', 'hidden', 'important');
                modal.style.setProperty('opacity', '0', 'important');
                modal.classList.remove('show', 'active');

                document.body.style.setProperty('overflow', '', 'important');
                document.body.style.setProperty('overflow-y', '', 'important');
            }
        };
    }

    if (typeof window.goToPlans === 'undefined') {
        window.goToPlans = function() {
            window.closeUpgradeModal();
            sessionStorage.setItem('navigateToPlans', 'true');
            window.location.href = '/profile';
        };
    }
})();

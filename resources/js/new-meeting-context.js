//
// Utilidades para hidratar el contexto global de la página de Nueva Reunión.
// La idea es tomar los valores que vienen en data-attributes del <body> y
// colocarlos en window, para que el resto de los módulos los use sin depender
// directamente del DOM.
//

function normalizeValue(value) {
    if (value === undefined || value === null || value === '') {
        return null;
    }
    return value;
}

function normalizeNumber(value) {
    const normalized = normalizeValue(value);
    if (normalized === null) {
        return null;
    }

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
}

function hydrateNewMeetingContext() {
    if (typeof document === 'undefined') {
        // Entorno sin DOM (tests o SSR): no hay nada que hidratar
        return;
    }

    const body = document.body;
    if (!body) {
        // Si el body aún no está disponible, salir silenciosamente
        return;
    }

    const dataset = body.dataset || {};
    const role = normalizeValue(dataset.userRole);
    const organizationId = normalizeNumber(dataset.organizationId);
    const organizationName = normalizeValue(dataset.organizationName);
    const planCode = normalizeValue(dataset.userPlanCode) || 'free';
    const userId = normalizeNumber(dataset.userId);
    const userName = normalizeValue(dataset.userName);

    if (typeof window.userRole === 'undefined' || window.userRole === null) {
        window.userRole = role;
    }

    if (typeof window.currentOrganizationId === 'undefined' || window.currentOrganizationId === null) {
        window.currentOrganizationId = organizationId;
    }

    if (typeof window.currentOrganizationName === 'undefined' || window.currentOrganizationName === null) {
        window.currentOrganizationName = organizationName;
    }

    if (typeof window.organizationId === 'undefined' || window.organizationId === null) {
        window.organizationId = organizationId;
    }

    if (typeof window.userPlanCode === 'undefined' || window.userPlanCode === null) {
        window.userPlanCode = planCode;
    }

    if (typeof window.userId === 'undefined' || window.userId === null) {
        window.userId = userId;
    }

    if (typeof window.userName === 'undefined' || window.userName === null) {
        window.userName = userName;
    }
}

hydrateNewMeetingContext();

document.addEventListener('DOMContentLoaded', hydrateNewMeetingContext);

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
        return;
    }

    const body = document.body;
    if (!body) {
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

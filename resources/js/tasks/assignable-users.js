const SOURCE_LABELS = {
    contact: 'Contacto',
    organization: 'Organización',
    shared: 'Compartidos',
};

function resolveCsrfToken() {
    return (
        window.taskLaravel?.csrf ||
        document.querySelector('meta[name="csrf-token"]')?.content ||
        ''
    );
}

export function getSourceLabel(source) {
    if (!source) {
        return SOURCE_LABELS.contact;
    }
    return SOURCE_LABELS[source] || SOURCE_LABELS.contact;
}

export function buildOptionLabel(user) {
    if (!user) {
        return '';
    }
    const name = user.name || user.email || '';
    const label = getSourceLabel(user.source);
    return name ? `${name} (${label})` : `(${label})`;
}

export function groupUsersBySource(users = []) {
    const buckets = {
        contact: [],
        organization: [],
        shared: [],
        other: [],
    };

    users.forEach(user => {
        if (!user) {
            return;
        }
        const source = user.source;
        if (source === 'contact') {
            buckets.contact.push(user);
        } else if (source === 'organization') {
            buckets.organization.push(user);
        } else if (source === 'shared') {
            buckets.shared.push(user);
        } else {
            buckets.other.push(user);
        }
    });

    return buckets;
}

const cachedAssignableUsers = new Map();

function getCacheKey(meetingId) {
    return meetingId ? String(meetingId) : 'default';
}

export async function loadAssignableUsers(meetingId = null, { forceRefresh = false } = {}) {
    const cacheKey = getCacheKey(meetingId);
    if (!forceRefresh && cachedAssignableUsers.has(cacheKey)) {
        return cachedAssignableUsers.get(cacheKey);
    }

    try {
        const url = new URL('/api/tasks-laravel/assignable-users', window.location.origin);
        if (meetingId) {
            url.searchParams.set('meeting_id', meetingId);
        }
        const headers = {
            Accept: 'application/json',
        };
        const csrfToken = resolveCsrfToken();
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(url, { headers });
        const data = await response.json();
        const users = Array.isArray(data.users) ? data.users : [];
        cachedAssignableUsers.set(cacheKey, users);
        return users;
    } catch (error) {
        console.error('Error loading assignable users:', error);
        cachedAssignableUsers.set(cacheKey, []);
        return [];
    }
}

export function populateAssigneeSelector(selectElement, users = [], currentId = null) {
    if (!selectElement) {
        return;
    }

    const previousValue = currentId ? String(currentId) : '';
    selectElement.innerHTML = '<option value="">Selecciona contacto o miembro</option>';

    users.forEach(user => {
        if (!user || !user.id) {
            return;
        }
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = buildOptionLabel(user);
        option.dataset.email = user.email || '';
        option.dataset.source = user.source || '';
        option.dataset.name = user.name || '';
        selectElement.appendChild(option);
    });

    if (previousValue && Array.from(selectElement.options).some(opt => opt.value === previousValue)) {
        selectElement.value = previousValue;
    } else {
        selectElement.value = '';
    }

    selectElement.disabled = users.length <= 0;
}

export function clearAssignableUsersCache(meetingId = undefined) {
    if (typeof meetingId === 'undefined') {
        cachedAssignableUsers.clear();
        return;
    }

    cachedAssignableUsers.delete(getCacheKey(meetingId));
}

export const AssignableUsersUtils = {
    SOURCE_LABELS,
    getSourceLabel,
    buildOptionLabel,
    groupUsersBySource,
};

export const AssignableUsersManager = {
    loadAssignableUsers,
    populateAssigneeSelector,
    clearAssignableUsersCache,
    cachedAssignableUsers,
};

if (typeof window !== 'undefined') {
    window.AssignableUsersUtils = AssignableUsersUtils;
    window.AssignableUsersManager = AssignableUsersManager;
}

export default AssignableUsersUtils;
export { SOURCE_LABELS };

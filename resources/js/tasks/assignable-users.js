const SOURCE_LABELS = {
    contact: 'Contacto',
    organization: 'Organización',
    shared: 'Compartidos',
    platform: 'Usuarios de Juntify',
};

const DEFAULT_SOURCE = 'contact';

function normalizeOption(rawUser) {
    if (!rawUser || typeof rawUser !== 'object') {
        return null;
    }

    const id = rawUser.id ?? rawUser.user_id ?? null;
    if (!id) {
        return null;
    }

    const name = rawUser.name || rawUser.full_name || rawUser.email || '';
    const email = rawUser.email || '';
    const source = rawUser.source || rawUser.platform || DEFAULT_SOURCE;

    return {
        id: String(id),
        name,
        email,
        source,
        platform: rawUser.platform || null,
    };
}

function resolveCsrfToken() {
    return (
        window.taskLaravel?.csrf ||
        document.querySelector('meta[name="csrf-token"]')?.content ||
        ''
    );
}

export function getSourceLabel(source) {
    if (!source) {
        return SOURCE_LABELS[DEFAULT_SOURCE];
    }
    return SOURCE_LABELS[source] || SOURCE_LABELS[DEFAULT_SOURCE];
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
        platform: [],
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
        } else if (source === 'platform') {
            buckets.platform.push(user);
        } else {
            buckets.other.push(user);
        }
    });

    return buckets;
}

const cachedAssignableUsers = new Map();

function getCacheKey(meetingId, query = '') {
    const base = meetingId ? String(meetingId) : 'default';
    const normalizedQuery = (query || '').trim().toLowerCase();
    return `${base}::${normalizedQuery}`;
}

export async function loadAssignableUsers(meetingId = null, { forceRefresh = false, query = '' } = {}) {
    const normalizedQuery = (query || '').trim();
    const cacheKey = getCacheKey(meetingId, normalizedQuery);
    if (!forceRefresh && cachedAssignableUsers.has(cacheKey)) {
        return cachedAssignableUsers.get(cacheKey);
    }

    try {
        const url = new URL('/api/tasks-laravel/assignable-users', window.location.origin);
        if (meetingId) {
            url.searchParams.set('meeting_id', meetingId);
        }
        if (normalizedQuery) {
            url.searchParams.set('query', normalizedQuery);
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
        const users = Array.isArray(data.users)
            ? data.users.map(normalizeOption).filter(Boolean)
            : [];
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
        if (user.platform) {
            option.dataset.platform = user.platform;
        }
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

    const base = meetingId ? String(meetingId) : 'default';
    Array.from(cachedAssignableUsers.keys()).forEach(key => {
        if (key.startsWith(`${base}::`)) {
            cachedAssignableUsers.delete(key);
        }
    });
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

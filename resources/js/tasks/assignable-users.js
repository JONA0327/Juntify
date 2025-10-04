const SOURCE_LABELS = {
    contact: 'Contacto',
    organization: 'Organización',
    shared: 'Compartidos',
};

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

export const AssignableUsersUtils = {
    SOURCE_LABELS,
    getSourceLabel,
    buildOptionLabel,
    groupUsersBySource,
};

if (typeof window !== 'undefined') {
    window.AssignableUsersUtils = AssignableUsersUtils;
}

export default AssignableUsersUtils;
export { SOURCE_LABELS };

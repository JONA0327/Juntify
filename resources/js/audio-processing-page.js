const parseJsonData = (value) => {
    if (value === undefined) {
        return null;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        return value ?? null;
    }
};

const body = document.body;

if (body) {
    const { userRole, currentOrganizationId } = body.dataset;

    window.userRole = parseJsonData(userRole);
    window.currentOrganizationId = parseJsonData(currentOrganizationId);
}

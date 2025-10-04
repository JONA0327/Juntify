import test from 'node:test';
import assert from 'node:assert/strict';

import { buildOptionLabel, getSourceLabel, groupUsersBySource, SOURCE_LABELS } from '../tasks/assignable-users.js';

test('getSourceLabel returns localized labels for known sources', () => {
    assert.equal(getSourceLabel('contact'), SOURCE_LABELS.contact);
    assert.equal(getSourceLabel('organization'), SOURCE_LABELS.organization);
    assert.equal(getSourceLabel('shared'), SOURCE_LABELS.shared);
    assert.equal(getSourceLabel('platform'), SOURCE_LABELS.platform);
});

test('buildOptionLabel includes the category label in the option text', () => {
    const contactOption = buildOptionLabel({ name: 'Ana', source: 'contact' });
    const orgOption = buildOptionLabel({ name: 'Luis', source: 'organization' });
    const sharedOption = buildOptionLabel({ name: 'Marta', source: 'shared' });
    const platformOption = buildOptionLabel({ name: 'Camila', source: 'platform' });

    assert.match(contactOption, /Contacto/);
    assert.match(orgOption, /Organización/);
    assert.match(sharedOption, /Compartidos/);
    assert.match(platformOption, /Usuarios de Juntify/);
});

test('groupUsersBySource groups users in the expected categories', () => {
    const users = [
        { id: 1, source: 'contact' },
        { id: 2, source: 'organization' },
        { id: 3, source: 'shared' },
        { id: 4, source: 'platform' },
        { id: 5, source: 'unknown' },
    ];

    const grouped = groupUsersBySource(users);

    assert.equal(grouped.contact.length, 1);
    assert.equal(grouped.organization.length, 1);
    assert.equal(grouped.shared.length, 1);
    assert.equal(grouped.platform.length, 1);
    assert.equal(grouped.other.length, 1);
});

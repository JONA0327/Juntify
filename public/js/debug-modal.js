// Script de verificación de errores JavaScript para AI Assistant
console.log('=== Verificación de Funciones del Modal de Contexto ===');

// Verificar funciones principales del modal
const functionsToCheck = [
    'openContextSelector',
    'closeContextSelector',
    'switchContextType',
    'filterContextItems',
    'selectAllMeetings',
    'updateSessionInfo'
];

functionsToCheck.forEach(funcName => {
    if (typeof window[funcName] === 'function') {
        console.log(`✅ ${funcName} - EXISTE`);
    } else {
        console.log(`❌ ${funcName} - NO EXISTE`);
    }
});

// Verificar elementos del DOM
const elementsToCheck = [
    'contextSelectorModal',
    'contextSearchInput',
    'containersView',
    'meetingsView',
    'loadedContextItems'
];

console.log('\n=== Verificación de Elementos DOM ===');
elementsToCheck.forEach(elementId => {
    const element = document.getElementById(elementId);
    if (element) {
        console.log(`✅ #${elementId} - EXISTE`);
    } else {
        console.log(`❌ #${elementId} - NO EXISTE`);
    }
});

// Verificar variables globales
console.log('\n=== Verificación de Variables Globales ===');
const variablesToCheck = [
    'currentSessionId',
    'currentContextType',
    'loadedContextItems'
];

variablesToCheck.forEach(varName => {
    if (typeof window[varName] !== 'undefined') {
        console.log(`✅ ${varName} - EXISTE:`, window[varName]);
    } else {
        console.log(`❌ ${varName} - NO EXISTE`);
    }
});

// Intentar abrir el modal de contexto
console.log('\n=== Test de Modal ===');
try {
    if (typeof openContextSelector === 'function') {
        console.log('🧪 Probando apertura de modal...');
        // No llamamos la función para evitar interferir, solo verificamos que esté disponible
        console.log('✅ Modal disponible para apertura');
    }
} catch (error) {
    console.error('❌ Error al intentar abrir modal:', error);
}

console.log('\n=== Verificación Completa ===');
console.log('Si todos los elementos están marcados con ✅, el modal debería funcionar correctamente.');

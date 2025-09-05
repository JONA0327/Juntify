// Script de prueba para verificar el estado de descarte de audio

console.log('=== VERIFICACIÓN DE ESTADO DE DESCARTE ===');

// Verificar si el audio está marcado como descartado
try {
    const audioDiscarded = sessionStorage.getItem('audioDiscarded');
    console.log('🔍 Estado en sessionStorage:', audioDiscarded);

    if (audioDiscarded === 'true') {
        console.log('🚫 AUDIO DESCARTADO - El procesamiento debe estar bloqueado');
    } else {
        console.log('✅ AUDIO NO DESCARTADO - El procesamiento puede continuar');
    }
} catch (e) {
    console.error('❌ Error verificando estado:', e);
}

// Función para simular descarte
function simularDescarte() {
    sessionStorage.setItem('audioDiscarded', 'true');
    console.log('🚫 Audio marcado como descartado');
    console.log('💡 Ahora intenta navegar a /audio-processing - debería redirigirte automáticamente');
}

// Función para limpiar descarte
function limpiarDescarte() {
    sessionStorage.removeItem('audioDiscarded');
    console.log('✅ Estado de descarte limpiado');
    console.log('💡 Ahora el procesamiento debería funcionar normalmente');
}

// Hacer funciones disponibles globalmente
window.simularDescarte = simularDescarte;
window.limpiarDescarte = limpiarDescarte;

console.log('💡 Funciones disponibles:');
console.log('  - simularDescarte() - Para probar el bloqueo');
console.log('  - limpiarDescarte() - Para limpiar el estado');

console.log('🧪 Script de debug para modal');

// Función para debuggar el estado del modal
window.debugModal = function() {
    const modal = document.getElementById('upgrade-modal');
    console.log('🔍 Estado del modal:');
    console.log('- Modal exists:', !!modal);

    if (modal) {
        console.log('- Modal display:', modal.style.display);
        console.log('- Modal classList:', modal.classList.toString());
        console.log('- Listeners added:', modal._listenersAdded);
        console.log('- Escape handler:', !!modal._escapeHandler);

        const overlay = modal.querySelector('.modal-overlay');
        const closeBtn = modal.querySelector('#modal-close-btn');
        const cancelBtn = modal.querySelector('#modal-cancel-btn');
        const plansBtn = modal.querySelector('#modal-plans-btn');

        console.log('- Overlay exists:', !!overlay);
        console.log('- Close btn exists:', !!closeBtn);
        console.log('- Cancel btn exists:', !!cancelBtn);
        console.log('- Plans btn exists:', !!plansBtn);

        console.log('- Handlers stored:');
        console.log('  - _overlayHandler:', !!modal._overlayHandler);
        console.log('  - _closeBtnHandler:', !!modal._closeBtnHandler);
        console.log('  - _cancelBtnHandler:', !!modal._cancelBtnHandler);
        console.log('  - _plansBtnHandler:', !!modal._plansBtnHandler);
    }

    console.log('- closeUpgradeModal function:', typeof window.closeUpgradeModal);
    console.log('- showUpgradeModal function:', typeof window.showUpgradeModal);
};

// Función para forzar cierre del modal
window.forceCloseModal = function() {
    console.log('🔧 Forzando cierre del modal...');
    const modal = document.getElementById('upgrade-modal');
    if (modal) {
        // Remover completamente el modal del DOM
        modal.remove();
        console.log('✅ Modal removido del DOM');
    } else {
        console.log('❌ No se encontró modal para remover');
    }
};

// Agregar a la consola
console.log('✅ Funciones de debug agregadas:');
console.log('- window.debugModal() - Ver estado del modal');
console.log('- window.forceCloseModal() - Forzar cierre del modal');

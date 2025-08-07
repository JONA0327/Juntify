function changeTab(tabId) {
    // Ocultar todos los paneles con transición
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.style.opacity = '0';
        setTimeout(() => {
            panel.classList.add('hidden');
        }, 150);
    });
    
    // Actualizar estilos de botones
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('text-yellow-400', 'border-yellow-400');
        button.classList.add('text-slate-400', 'border-transparent');
    });
    
    // Mostrar panel activo con transición
    setTimeout(() => {
        const activePanel = document.getElementById(`panel-${tabId}`);
        const activeButton = document.getElementById(`tab-${tabId}`);
        
        activePanel.classList.remove('hidden');
        setTimeout(() => {
            activePanel.style.opacity = '1';
        }, 50);
        
        activeButton.classList.add('text-yellow-400', 'border-yellow-400');
        activeButton.classList.remove('text-slate-400', 'border-transparent');
    }, 150);
}

// Inicializar animaciones cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Asignar eventos a los botones de las pestañas
    document.getElementById('tab-my-meetings').addEventListener('click', () => changeTab('my-meetings'));
    document.getElementById('tab-shared-meetings').addEventListener('click', () => changeTab('shared-meetings'));
});

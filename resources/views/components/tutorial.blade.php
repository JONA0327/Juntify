@props([
    'page' => 'general',
    'autoStart' => true,
    'showHelpButton' => true
])

@push('styles')
<!-- Shepherd.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@13.0.3/dist/css/shepherd.css">
@endpush

@push('scripts')
<!-- Tutorial initialization script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración del tutorial para esta página
    const tutorialConfig = {
        page: '{{ $page }}',
        autoStart: {{ $autoStart ? 'true' : 'false' }},
        showHelpButton: {{ $showHelpButton ? 'true' : 'false' }},
        apiEndpoints: {
            status: '{{ route("tutorial.status") }}',
            progress: '{{ route("tutorial.progress") }}',
            preferences: '{{ route("tutorial.preferences") }}',
            reset: '{{ route("tutorial.reset") }}',
            config: '{{ route("tutorial.config") }}'
        },
        csrfToken: '{{ csrf_token() }}'
    };

    // Aplicar configuración al tutorial si existe
    if (window.juntifyTutorial) {
        window.juntifyTutorial.config = tutorialConfig;

        // Cargar configuración del servidor
        fetch(tutorialConfig.apiEndpoints.config + '?page=' + encodeURIComponent(tutorialConfig.page))
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const serverConfig = data.data;

                    // Auto-iniciar si está configurado
                    if (serverConfig.should_auto_start && tutorialConfig.autoStart) {
                        setTimeout(() => {
                            window.juntifyTutorial.startTour();
                        }, 2000); // Esperar 2 segundos para que cargue la página
                    }
                }
            })
            .catch(error => {
                console.warn('Error al cargar configuración del tutorial:', error);
            });
    }
});

// Función global para controlar el tutorial
window.startTutorial = function() {
    if (window.juntifyTutorial) {
        window.juntifyTutorial.startTour();
    }
};

window.resetTutorial = function() {
    if (window.juntifyTutorial && confirm('¿Estás seguro de que quieres reiniciar el tutorial?')) {
        fetch('{{ route("tutorial.reset") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Tutorial reiniciado correctamente');
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error al reiniciar tutorial:', error);
        });
    }
};
</script>
@endpush

<!-- Botón de ayuda flotante (se añade automáticamente por JavaScript) -->

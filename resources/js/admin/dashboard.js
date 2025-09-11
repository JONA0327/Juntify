document.getElementById('process-pending-recordings')?.addEventListener('click', () => {
    fetch('/admin/pending-recordings/process', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
        .then(() => alert('Proceso iniciado'))
        .catch(() => alert('Error al iniciar el proceso'));
});

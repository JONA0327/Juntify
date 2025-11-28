(function() {
    let currentSelectedContainerId = null;

    function setActiveContainerForFiles(containerId) {
        currentSelectedContainerId = containerId;
        const widget = document.getElementById('containerFilesWidget');
        if (widget) {
            widget.style.display = 'block';
        }
        refreshContainerFiles();
    }

    function triggerContainerFileUpload() {
        if (!currentSelectedContainerId) {
            alert('Selecciona un contenedor primero');
            return;
        }
        const input = document.getElementById('containerFileInput');
        if (input) {
            input.value = '';
            input.click();
        }
    }

    async function uploadContainerFile(files) {
        if (!files || !files.length) return;
        const file = files[0];
        if (file.size > 150 * 1024 * 1024) {
            alert('Archivo excede 150MB');
            return;
        }

        const statusEl = document.getElementById('containerFilesStatus');
        if (statusEl) {
            statusEl.textContent = 'Subiendo...';
        }
        const formData = new FormData();
        formData.append('file', file);
        try {
            const resp = await fetch(`/containers/${currentSelectedContainerId}/files`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                body: formData
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({ message: 'Error desconocido' }));
                if (statusEl) {
                    statusEl.textContent = 'Error: ' + (err.message || 'falló la subida');
                }
                return;
            }
            if (statusEl) {
                statusEl.textContent = 'Archivo subido correctamente';
            }
            refreshContainerFiles();
        } catch (e) {
            if (statusEl) {
                statusEl.textContent = 'Error de red al subir';
            }
        }
    }

    async function refreshContainerFiles() {
        if (!currentSelectedContainerId) return;
        const tbody = document.querySelector('#containerFilesTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5">Cargando...</td></tr>';
        try {
            const resp = await fetch(`/containers/${currentSelectedContainerId}/files`);
            const data = await resp.json();
            tbody.innerHTML = '';
            (data.data || []).forEach(f => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${escapeHtml(f.name)}</td>
                <td>${(f.size/1024/1024).toFixed(2)} MB</td>
                <td>${f.mime || ''}</td>
                <td>${f.uploaded_at}</td>
                <td><a class="btn btn-link" href="${f.url}" target="_blank">Descargar</a></td>
            `;
                tbody.appendChild(tr);
            });
            if (!tbody.children.length) {
                tbody.innerHTML = '<tr><td colspan="5">Sin archivos</td></tr>';
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5">Error cargando archivos</td></tr>';
        }
    }

    function escapeHtml(str) {
        return str.replace(/[&<>\"]?/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c));
    }

    window.setActiveContainerForFiles = setActiveContainerForFiles;
    window.triggerContainerFileUpload = triggerContainerFileUpload;
    window.uploadContainerFile = uploadContainerFile;
    window.refreshContainerFiles = refreshContainerFiles;
})();

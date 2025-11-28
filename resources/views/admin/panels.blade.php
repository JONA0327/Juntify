<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administrar Paneles - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/js/profile.js',
        'resources/js/admin-panels.js',
    ])
</head>
<body class="admin-panels-page">
    <div class="particles" id="particles"></div>

    @include('partials.navbar')

    <div class="app-container">
        <main class="main-admin">
            <div class="content-header">
                <div>
                    <h1 class="page-title">Administrar paneles</h1>
                    <p class="page-subtitle">Da de alta empresas y asigna administradores dedicados</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='{{ route('admin.dashboard') }}'">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver al panel
                    </button>
                    <button class="btn btn-primary" id="create-panel-btn">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Crear nuevo panel
                    </button>
                </div>
            </div>

            <div id="admin-panels-alert" class="hidden"></div>

            <div class="info-card" style="padding: 0; overflow: hidden;">
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Administrador</th>
                                <th>Email</th>
                                <th>Rol actual</th>
                                <th>URL del panel</th>
                                <th>Creado</th>
                            </tr>
                        </thead>
                        <tbody id="panels-table-body">
                            <tr>
                                <td colspan="6" class="text-center py-6 text-slate-400">Cargando paneles...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="create-panel-modal" class="modal hidden">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Nuevo panel empresarial
                </h3>
                <button type="button" class="modal-close" data-close-panel-modal>&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-panel-form">
                    <div class="form-group">
                        <label class="form-label" for="company-name">Nombre de la empresa</label>
                        <input id="company-name" type="text" class="modal-input" placeholder="Ingresa el nombre legal o comercial" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="panel-admin">Administrador</label>
                        <select id="panel-admin" class="modal-input" required>
                            <option value="" disabled selected>Selecciona un usuario</option>
                        </select>
                        <small class="input-hint">Solo usuarios con roles Enterprise, Founder o Developer.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="panel-url">URL del panel <span class="text-slate-400">(opcional)</span></label>
                        <input id="panel-url" type="url" class="modal-input" placeholder="https://empresa.juntify.com" maxlength="255">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-close-panel-modal>Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="confirm-create-panel">Crear panel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

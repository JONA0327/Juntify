import Alpine from 'alpinejs';

const DEBUG = import.meta.env.DEV;

function debugLog(...args) {
    if (DEBUG) {
        console.log(...args);
    }
}

Alpine.data('organizationPage', (initialOrganizations = []) => ({
    organizations: initialOrganizations,
    showOrgModal: false,
    showGroupModal: false,
    showGroupInfoModal: false,
    showInviteOptions: false,
    showEditOrgModal: false,
    showEditGroupModal: false,
    showInviteModal: false,
    showCreateContainerModal: false, // Nueva variable para modal crear contenedor
    showEditContainerModal: false, // Nueva variable para modal editar contenedor
    showContainerMeetingsModal: false, // Nueva variable para modal ver reuniones del contenedor
    // Confirmación de borrado de grupo
    showConfirmDeleteGroupModal: false,
    groupToDelete: null,
    orgOfGroupToDelete: null,
    isDeletingGroup: false,
    // Confirmación de borrado de subcarpeta (Drive)
    showConfirmDeleteSubfolderModal: false,
    subfolderToDelete: null,
    orgOfSubfolderToDelete: null,
    isDeletingSubfolder: false,
    showUploadDocumentsModal: false,
    showViewDocumentsModal: false,
    uploadDocumentGroup: null,
    viewDocumentsGroup: null,
    groupDocuments: [],
    isLoadingGroupDocuments: false,
    groupDocumentsError: null,
    documentUploadFile: null,
    isUploadingDocument: false,
    // Drag & upload UI state
    isDraggingFile: false,
    uploadProgress: 0,
    // Confirmación de expulsión de miembro
    showConfirmRemoveMemberModal: false,
    memberToRemove: null,
    groupIdForMemberRemoval: null,
    isRemovingMember: false,
    selectedContainer: null, // Nueva variable para el contenedor seleccionado
    mainTab: 'organization', // Nueva variable para las pestañas principales
    newOrg: {
        nombre_organizacion: '',
        descripcion: ''
    },
    preview: null,
    editOrg: {
        id: null,
        nombre_organizacion: '',
        descripcion: ''
    },
    editPreview: null,
    newGroup: {
        nombre_grupo: '',
        descripcion: '',
        id_organizacion: null
    },
    newContainer: { // Nueva variable para contenedor
        name: '',
        description: ''
    },
    editContainer: { // Nueva variable para editar contenedor
        id: null,
        name: '',
        description: ''
    },
    editGroup: {
        id: null,
        nombre_grupo: '',
        descripcion: '',
        id_organizacion: null
    },
    currentOrg: null,
    currentGroup: null,
    selectedGroup: null, // Nueva variable para el grupo seleccionado para invitación
    inviteEmail: '',
    inviteCode: '',
    inviteRole: 'invitado', // Nueva variable para el rol de invitación
    userExists: null,
    userExistsMessage: '',
    selectedOrganization: null, // Nueva variable para la organización seleccionada
    editForm: {
        nombre_organizacion: '',
        descripcion: '',
        imagen: '',
        newImagePreview: null,
        newImageFile: null
    },
    editGroupForm: {
        nombre_grupo: '',
        descripcion: ''
    },
    showEditModal: false,
    isCreatingOrg: false, // Nueva variable para loading de crear organización
    isCreatingGroup: false, // Nueva variable para loading de crear grupo
    isCreatingContainer: false, // Nueva variable para loading de crear contenedor
    isLoadingGroup: false, // Nueva variable para loading de ver grupo
    groupError: null, // Mensaje de error al cargar grupo
    isJoining: false, // Nueva variable para loading de unirse
    isSavingGroup: false, // Nueva variable para loading de guardar grupo
    isSavingOrganization: false, // Nueva variable para loading de guardar organización
    isSendingInvitation: false, // Nueva variable para loading de enviar invitación
    showSuccessModal: false, // Nueva variable para modal de éxito
    successMessage: '', // Nueva variable para mensaje de éxito
    isErrorModal: false, // Nueva variable para distinguir entre éxito y error
    userId: null, // Se inicializará en init()
    activeTab: 'contenedores', // Cambiar tab por defecto a contenedores
    activities: {},
    driveData: {},
    isOwner: false,
    showAlert: false,
    alertMessage: '',
    alertType: 'success',
    alertTimeout: null,
    containerModalRestoreContext: null,

    // NUEVO: contactos invitables para el modal
    invitableContacts: [],
    isLoadingInvitableContacts: false,
    invitableContactsError: null,
    inviteContactSearch: '',

    // Utilidad: formateador de fechas robusto para cadenas variadas
    formatDate(value) {
        if (!value) return '';
        // Si ya es Date
        if (value instanceof Date) {
            return isNaN(value) ? '' : value.toLocaleDateString();
        }
        // Timestamps numéricos
        if (typeof value === 'number') {
            const d = new Date(value);
            return isNaN(d) ? '' : d.toLocaleDateString();
        }
        // Cadenas: normalizar "YYYY-MM-DD HH:MM:SS" a ISO
        if (typeof value === 'string') {
            let s = value.trim();
            if (!s) return '';
            // Reemplazar espacio por 'T' si faltara la 'T'
            if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(s)) {
                s = s.replace(' ', 'T');
            }
            // Agregar 'Z' si parece ISO sin zona
            if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(s)) {
                s = s + 'Z';
            }
            const d = new Date(s);
            if (!isNaN(d)) return d.toLocaleDateString();
            // Fallback: intentar parsear solo fecha
            const onlyDate = s.split('T')[0];
            const d2 = new Date(onlyDate);
            return isNaN(d2) ? '' : d2.toLocaleDateString();
        }
        return '';
    },

    get filteredInvitableContacts() {
        const term = (this.inviteContactSearch || '').toLowerCase();
        if (!term) return this.invitableContacts;
        return this.invitableContacts.filter(c =>
            c.name.toLowerCase().includes(term) || c.email.toLowerCase().includes(term)
        );
    },

    async loadInvitableContacts() {
        if (!this.selectedGroup) return;
        this.isLoadingInvitableContacts = true;
        this.invitableContactsError = null;
        try {
            const res = await fetch(`/api/groups/${this.selectedGroup.id}/invitable-contacts`);
            if (!res.ok) {
                this.invitableContactsError = 'No se pudieron cargar tus contactos';
                this.invitableContacts = [];
                return;
            }
            const data = await res.json();
            this.invitableContacts = (data.contacts || []).sort((a,b) => a.name.localeCompare(b.name));
        } catch (e) {
            console.error('Error loading invitable contacts', e);
            this.invitableContactsError = 'Error de red al cargar contactos';
            this.invitableContacts = [];
        } finally {
            this.isLoadingInvitableContacts = false;
        }
    },

    selectInvitableContact(contact) {
        if (contact.blocked) {
            this.showError('Este contacto pertenece a otra organización');
            return;
        }
        this.inviteEmail = contact.email;
        this.userExists = true; // Es usuario existente
        this.userExistsMessage = '✓ Este usuario existe en Juntify';
    },

    canManageContainers() {
        // Backend may return current_user_role; also allow org owner
        const role = this.currentGroup?.user_role || this.currentGroup?.current_user_role;
        const isOwner = !!this.currentGroup?.organization_is_owner;
        return isOwner || ['administrador', 'colaborador'].includes(role);
    },

    async loadActivities(orgId) {
        try {
            const response = await fetch(`/api/organization-activities?organization_id=${orgId}`);
            if (response.ok) {
                const data = await response.json();
                this.activities[orgId] = data.activities;
            } else {
                this.activities[orgId] = [];
            }
        } catch (error) {
            console.error('Error loading activities:', error);
            this.activities[orgId] = [];
        }
    },

    getDriveState(orgId) {
        if (!this.driveData[orgId]) {
            this.driveData[orgId] = {
                rootFolder: null,
                subfolders: [],
                connected: false,
                isLoading: false,
                isCreatingRoot: false,
                isCreatingSubfolder: false,
                newSubfolderName: '',
                selectedFolderId: null,
                folderFiles: [],
                isLoadingFolderFiles: false,
                folderError: null,
            };
        }
        return this.driveData[orgId];
    },

    connectDrive(org) {
        // Include source so callback redirects back to organizations
        // Pasamos el organization_id de la organización específica
        if (org && org.id) {
            window.location.href = `/auth/google/redirect?from=organization&organization_id=${org.id}`;
        } else {
            this.showStatus('Error: No se pudo identificar la organización', 'error');
        }
    },

    async disconnectDrive(org) {
        try {
            if (!org || !org.id) {
                this.showStatus('Error: No se pudo identificar la organización', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('organization_id', org.id);

            const res = await fetch('/drive/disconnect-organization', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            });

            if (res.ok) {
                // Refresh drive status for this specific org
                await this.loadDriveSubfolders(org);
                this.showStatus('Google Drive organizacional desconectado');
            } else {
                this.showStatus('No se pudo desconectar', 'error');
            }
        } catch (e) {
            console.error('Error disconnecting drive', e);
            this.showStatus('Error al desconectar', 'error');
        }
    },

    async createOrganizationFolder(org) {
        const state = this.getDriveState(org.id);
        if (state.isCreatingRoot) return;
        state.isCreatingRoot = true;
        try {
            const response = await fetch(`/api/organizations/${org.id}/drive/root-folder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
            });
            if (response.ok) {
                const data = await response.json();
                state.rootFolder = data.folder;
                this.showStatus('Carpeta de organización creada correctamente');
                await this.loadDriveSubfolders(org);
            } else {
                let msg = 'Error al crear la carpeta de organización';
                try {
                    const err = await response.json();
                    msg = err.message || msg;
                } catch {}
                this.showStatus(msg, 'error');
            }
        } catch (e) {
            console.error('Error creating organization folder:', e);
            this.showStatus('Error al crear la carpeta de organización', 'error');
        } finally {
            state.isCreatingRoot = false;
        }
    },

    async loadDriveSubfolders(org) {
        // Subcarpetas gestionadas automáticamente: sólo necesitamos estado conexión y root
        const state = this.getDriveState(org.id);
        state.isLoading = true;
        try {
            const statusRes = await fetch(`/api/organizations/${org.id}/drive/status`);
            if (statusRes.ok) {
                const status = await statusRes.json();
                state.connected = !!status.connected;
                state.rootFolder = status.root_folder || null;
                state.subfolders = Array.isArray(status.subfolders) ? status.subfolders : [];
                if (state.connected && (org.is_owner || org.user_role === 'administrador')) {
                    try {
                        const foldersRes = await fetch(`/api/organizations/${org.id}/documents/folders`);
                        if (foldersRes.ok) {
                            const foldersData = await foldersRes.json();
                            state.subfolders = Array.isArray(foldersData.folders) ? foldersData.folders : state.subfolders;
                        }
                    } catch (error) {
                        console.error('Error cargando carpetas de la organización', error);
                    }
                }
                if (!state.connected) {
                    state.folderFiles = [];
                    state.selectedFolderId = null;
                }
            }
        } catch (e) {
            console.error('Error loading drive status:', e);
            state.rootFolder = null;
        } finally {
            state.isLoading = false;
        }
    },

    async createSubfolder(org) {
        console.warn('Creación manual de subcarpetas deshabilitada (estructura automática).');
        this.showStatus('Las subcarpetas se gestionan automáticamente', 'info');
    },

    // New: start editing a subfolder
    startEditSubfolder(sf) {
        sf._isEditing = true;
        sf._editingName = sf.name;
    },

    // New: save rename in Drive then DB
    async saveSubfolderName(org, sf) {
        const newName = (sf._editingName || '').trim();
        if (!newName) {
            this.showStatus('El nombre no puede estar vacío', 'error');
            return;
        }
        try {
            const res = await fetch(`/api/organizations/${org.id}/drive/subfolders/${sf.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ name: newName })
            });
            if (res.ok) {
                const updated = await res.json();
                sf.name = updated.name;
                sf._isEditing = false;
                this.showStatus('Subcarpeta renombrada');
            } else {
                let msg = 'No se pudo renombrar';
                try { const err = await res.json(); msg = err.message || msg; } catch {}
                this.showStatus(msg, 'error');
            }
        } catch (e) {
            console.error('rename subfolder error', e);
            this.showStatus('Error al renombrar', 'error');
        }
    },

    // New: delete subfolder (Drive first, then DB) - called by modal confirm
    async deleteSubfolder(org, sf) {
        try {
            const res = await fetch(`/api/organizations/${org.id}/drive/subfolders/${sf.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
            });
            if (res.ok) {
                // Optimistic removal
                const state = this.getDriveState(org.id);
                state.subfolders = (state.subfolders || []).filter(x => String(x.id) !== String(sf.id));
                this.showStatus('Subcarpeta eliminada');
                return true;
            } else {
                let msg = 'No se pudo eliminar';
                try { const err = await res.json(); msg = err.message || msg; } catch {}
                this.showStatus(msg, 'error');
                return false;
            }
        } catch (e) {
            console.error('delete subfolder error', e);
            this.showStatus('Error al eliminar', 'error');
            return false;
        }
    },

    // Modal handlers for subfolder deletion
    openConfirmDeleteSubfolder(org, sf) {
        this.orgOfSubfolderToDelete = org;
        this.subfolderToDelete = sf;
        this.showConfirmDeleteSubfolderModal = true;
    },
    closeConfirmDeleteSubfolder() {
        this.showConfirmDeleteSubfolderModal = false;
        this.subfolderToDelete = null;
        this.orgOfSubfolderToDelete = null;
        this.isDeletingSubfolder = false;
    },
    async confirmDeleteSubfolder() {
        if (!this.orgOfSubfolderToDelete || !this.subfolderToDelete) return;
        if (this.isDeletingSubfolder) return;
        this.isDeletingSubfolder = true;
        const ok = await this.deleteSubfolder(this.orgOfSubfolderToDelete, this.subfolderToDelete);
        this.isDeletingSubfolder = false;
        if (ok) {
            this.closeConfirmDeleteSubfolder();
        }
    },

    canUploadDocuments(org, group) {
        if (!org || !group) return false;
        if (org.is_owner || org.user_role === 'administrador') return true;
        return ['administrador', 'colaborador'].includes(group.user_role || '');
    },

    openUploadDocumentsModal(group) {
        this.uploadDocumentGroup = group;
        this.documentUploadFile = null;
        this.isDraggingFile = false;
        this.uploadProgress = 0;
        this.showUploadDocumentsModal = true;
    },

    closeUploadDocumentsModal() {
        this.showUploadDocumentsModal = false;
        this.uploadDocumentGroup = null;
        this.documentUploadFile = null;
    },

    handleDocumentFileChange(event) {
        const files = event?.target?.files || [];
        const file = files.length ? files[0] : null;
        if (!file) { this.documentUploadFile = null; return; }
        // Optional validation: size <= 150MB (backend limit)
        const maxBytes = 150 * 1024 * 1024;
        if (file.size > maxBytes) {
            this.showStatus('El archivo supera los 150 MB permitidos', 'error');
            this.documentUploadFile = null;
            return;
        }
        this.documentUploadFile = file;
    },

    async uploadGroupDocument() {
        if (!this.uploadDocumentGroup || !this.documentUploadFile || this.isUploadingDocument) {
            return;
        }

        this.isUploadingDocument = true;
        this.uploadProgress = 0;
        const group = this.uploadDocumentGroup;
        const formData = new FormData();
        formData.append('file', this.documentUploadFile);

        try {
            // Use XHR to track upload progress
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `/api/groups/${group.id}/documents`);
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                    }
                };
                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        try {
                            const err = JSON.parse(xhr.responseText);
                            reject(new Error(err.message || 'No se pudo subir el documento'));
                        } catch {
                            reject(new Error('No se pudo subir el documento'));
                        }
                    }
                };
                xhr.onerror = () => reject(new Error('Fallo de red durante la subida'));
                xhr.send(formData);
            });

            this.showStatus('Documento subido correctamente');
            if (this.viewDocumentsGroup && this.viewDocumentsGroup.id === group.id) {
                await this.loadGroupDocuments(group.id);
            }
            this.closeUploadDocumentsModal();
        } catch (error) {
            console.error('Error al subir documento', error);
            this.showStatus(error?.message || 'Error al subir el documento', 'error');
        } finally {
            this.isUploadingDocument = false;
            this.uploadProgress = 0;
        }
    },

    openViewDocumentsModal(group) {
        this.viewDocumentsGroup = group;
        this.groupDocuments = [];
        this.groupDocumentsError = null;
        this.showViewDocumentsModal = true;
        this.loadGroupDocuments(group.id);
    },

    closeViewDocumentsModal() {
        this.showViewDocumentsModal = false;
        this.viewDocumentsGroup = null;
        this.groupDocuments = [];
        this.groupDocumentsError = null;
        this.isLoadingGroupDocuments = false;
    },

    async loadGroupDocuments(groupId) {
        if (!groupId) return;
        this.isLoadingGroupDocuments = true;
        this.groupDocumentsError = null;
        try {
            const response = await fetch(`/api/groups/${groupId}/documents`);
            if (!response.ok) {
                let msg = 'No se pudieron cargar los documentos';
                try {
                    const err = await response.json();
                    msg = err.message || msg;
                } catch {}
                this.groupDocumentsError = msg;
                this.groupDocuments = [];
                return;
            }
            const data = await response.json();
            this.groupDocuments = Array.isArray(data.files) ? data.files : [];
        } catch (error) {
            console.error('Error loading group documents', error);
            this.groupDocumentsError = 'Error al cargar los documentos del grupo';
            this.groupDocuments = [];
        } finally {
            this.isLoadingGroupDocuments = false;
        }
    },

    formatFileSize(bytes) {
        const size = Number(bytes || 0);
        if (!size) return '—';
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = size;
        let unit = 0;
        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }
        return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
    },

    formatDateTime(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            return date.toLocaleString();
        } catch {
            return dateString;
        }
    },

    async viewOrganizationFolder(org, folder) {
        if (!org || !folder) return;
        const state = this.getDriveState(org.id);
        state.selectedFolderId = folder.google_id;
        state.isLoadingFolderFiles = true;
        state.folderError = null;
        try {
            const response = await fetch(`/api/organizations/${org.id}/documents/folders/${folder.google_id}`);
            if (!response.ok) {
                let msg = 'No se pudo obtener el contenido de la carpeta';
                try {
                    const err = await response.json();
                    msg = err.message || msg;
                } catch {}
                state.folderError = msg;
                state.folderFiles = [];
                return;
            }
            const data = await response.json();
            state.folderFiles = Array.isArray(data.files) ? data.files : [];
        } catch (error) {
            console.error('Error loading organization folder', error);
            state.folderError = 'Error al cargar el contenido de la carpeta';
            state.folderFiles = [];
        } finally {
            state.isLoadingFolderFiles = false;
        }
    },

    // Método de inicialización para resetear estados
    init() {
        // Obtener el userId (UUID string) del meta tag sin convertir
        this.userId = document
            .querySelector('meta[name="user-id"]')
            .getAttribute('content');

    // Asegurar que el modal esté cerrado al iniciar
    this.showGroupInfoModal = false;

        this.showSuccessModal = false;
        this.successMessage = '';
        this.isErrorModal = false;
        this.isSavingGroup = false;
        this.isSavingOrganization = false;
        this.isSendingInvitation = false;
        this.isCreatingOrg = false;
        this.isCreatingGroup = false;
        this.isCreatingContainer = false;
        this.isLoadingGroup = false;
        this.isJoining = false;
    this.isDeletingGroup = false;

        if (import.meta.env.DEV) {
            console.log('Estado de organización reiniciado');
        }
        // Restaurar comportamiento: mostrar solo los grupos donde el usuario pertenece
        // Nota: El backend carga cada grupo con 'users' filtrado al usuario actual,
        // así que pertenencia = (group.users || []).length > 0
        if (Array.isArray(this.organizations)) {
            this.organizations = this.organizations.map(org => ({
                ...org,
                groups: (org.groups || []).filter(g => Array.isArray(g.users) && g.users.length > 0)
            }));
        } else {
            this.organizations = [];
        }

        if (!this._containerModalEventsBound) {
            const closeHandler = (event) => {
                const detail = event.detail || {};
                const containerFromDetail = detail.container ? { ...detail.container } : null;
                const containerId = detail.containerId
                    ?? containerFromDetail?.id
                    ?? this.selectedContainer?.id
                    ?? null;

                this.containerModalRestoreContext = {
                    containerId,
                    detail,
                    container: containerFromDetail
                        || (this.selectedContainer ? { ...this.selectedContainer } : null)
                };

                this.showContainerMeetingsModal = false;
            };

            const reopenHandler = (event) => {
                const detail = event.detail || {};
                const context = this.containerModalRestoreContext;

                if (!context) {
                    return;
                }

                const contextId = context.container?.id ?? context.containerId;

                if (!contextId) {
                    return;
                }

                const shouldReload = typeof detail.shouldReload === 'boolean'
                    ? detail.shouldReload
                    : (typeof context.detail?.shouldReload === 'boolean'
                        ? context.detail.shouldReload
                        : true);

                if (context.container && (!this.selectedContainer || this.selectedContainer.id !== context.container.id)) {
                    this.selectedContainer = context.container;
                } else if (!this.selectedContainer) {
                    this.selectedContainer = { id: contextId };
                }

                if (!this.selectedContainer) {
                    return;
                }

                const modalEl = document.getElementById('container-meetings-modal');
                if (modalEl) {
                    modalEl.classList.remove('hidden');
                    if (modalEl.style.pointerEvents === 'none') {
                        modalEl.style.pointerEvents = '';
                    }
                    if (modalEl.style.visibility === 'hidden') {
                        modalEl.style.visibility = '';
                    }
                    if (modalEl.style.opacity === '0') {
                        modalEl.style.opacity = '';
                    }
                    if (modalEl.getAttribute('aria-hidden') === 'true') {
                        modalEl.removeAttribute('aria-hidden');
                    }
                }

                this.showContainerMeetingsModal = true;

                if (shouldReload && this.selectedContainer.id) {
                    this.$nextTick(() => {
                        this.openContainerMeetingsModal(this.selectedContainer);
                    });
                }

                this.containerModalRestoreContext = null;
            };

            document.addEventListener('organization:container-meetings:temporarily-close', closeHandler);
            document.addEventListener('organization:container-meetings:restore', reopenHandler);

            this._containerModalEventsBound = true;
        }

        // Al abrir la pestaña de Permisos, cargar la lista completa de miembros por grupo
        this.$watch('mainTab', (tab) => {
            if (tab === 'permissions') {
                this.loadPermissionsMembers();
            }
        });
    },

    async loadPermissionsMembers() {
        try {
            if (!Array.isArray(this.organizations)) return;
            for (const org of this.organizations) {
                if (!Array.isArray(org.groups)) continue;
                for (const group of org.groups) {
                    // Evitar recargas múltiples
                    if (group._membersLoaded) continue;
                    try {
                        const res = await fetch(`/api/groups/${group.id}`);
                        if (res.ok) {
                            const data = await res.json();
                            // Sustituir por la lista completa de usuarios
                            group.users = Array.isArray(data.users) ? data.users : [];
                            // Mantener código disponible
                            if (!group.code && data.code) {
                                group.code = data.code;
                            }
                            group._membersLoaded = true;
                        } else {
                            group._membersLoaded = true;
                        }
                    } catch (e) {
                        console.error('Error loading members for group', group.id, e);
                        group._membersLoaded = true;
                    }
                }
            }
        } catch (e) {
            console.error('Error loading permissions members', e);
        }
    },
    openConfirmDeleteGroup(org, group) {
        thisorgOfGroupToDelete = org;
        this.groupToDelete = group;
        this.showConfirmDeleteGroupModal = true;
    },
    closeConfirmDeleteGroup() {
        this.showConfirmDeleteGroupModal = false;
        this.groupToDelete = null;
        this.orgOfGroupToDelete = null;
        this.isDeletingGroup = false;
    },

    openConfirmRemoveMember(user, groupId) {
        this.memberToRemove = user;
        this.groupIdForMemberRemoval = groupId;
        this.showConfirmRemoveMemberModal = true;
    },
    closeConfirmRemoveMember() {
        this.showConfirmRemoveMemberModal = false;
        this.memberToRemove = null;
        this.groupIdForMemberRemoval = null;
        this.isRemovingMember = false;
    },

    // Filtrar grupos de las organizaciones por usuario (comparación por UUID string)
    filterOrgGroups(orgs) {
        return orgs.map(org => ({
            ...org,
            groups: (org.groups || []).filter(group =>
                group.users && group.users.some(user => String(user.id) === String(this.userId))
            )
        }));

    },

    openOrgModal() {
        this.newOrg = { nombre_organizacion: '', descripcion: '' };
        this.preview = null;
        this.showOrgModal = true;
    },
    previewImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => { this.preview = e.target.result; };
            reader.readAsDataURL(file);
        }
    },
    async createOrganization() {
        if (this.isCreatingOrg) return; // Evitar múltiples clicks
        this.isCreatingOrg = true;
        let triedWebFallback = false;
        const attempt = async (url) => {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    nombre_organizacion: this.newOrg.nombre_organizacion,
                    descripcion: this.newOrg.descripcion,
                    imagen: this.preview
                })
            });
        };
        try {
            let response = await attempt('/api/organizations');
            if (response.status === 401 && !triedWebFallback) {
                console.warn('[organization.js] 401 en /api/organizations, intentando fallback /organizations (web)');
                triedWebFallback = true;
                response = await attempt('/organizations');
                console.log('[organization.js] Fallback /organizations status:', response.status, 'content-type:', response.headers.get('content-type'));
            }
            if (response.status === 401) {
                console.warn('[organization.js] 401 aún después de fallback. Diagnosticando sesión...');
                await this.debugAuth();
                const data401 = await response.json().catch(() => ({}));
                alert(data401.message || 'No autenticado');
                return;
            }
            // Detectar caso de redirección HTML (login) u otra respuesta no JSON
            const contentType = response.headers.get('content-type') || '';
            if (response.ok && !contentType.includes('application/json')) {
                console.warn('[organization.js] Respuesta OK pero no JSON (posible HTML de login). content-type:', contentType);
                const textSample = await response.text().catch(()=> '');
                console.debug('[organization.js] Primeros 120 chars de la respuesta:', textSample.substring(0,120));
                await this.debugAuth();
                alert('La sesión no fue reconocida (respuesta no JSON). Reintenta después de refrescar la página o volver a iniciar sesión.');
                return;
            }
            if (response.status === 419) { // CSRF / sesión expirada
                console.warn('[organization.js] 419 (CSRF/session) creando organización');
                await this.debugAuth();
                alert('La sesión expiró. Refresca la página e inténtalo de nuevo.');
                return;
            }
            if (response.status === 403) {
                const data = await response.json().catch(() => ({}));
                const reason = data.message || (window.userRole && ['free','basic'].includes(window.userRole)
                    ? 'Tu plan actual no permite crear organizaciones'
                    : 'Ya perteneces a una organización (solo se permite una)');
                console.warn('[organization.js] 403 creating organization', { userRole: window.userRole, organizationsCount: this.organizations.length, response: data });
                alert(reason);
                return;
            }
            if (response.ok) {
                const org = await response.json();
                org.imagen = this.preview;
                org.groups = [];
                this.organizations.push(org);
                this.showOrgModal = false;
                this.newOrg = { nombre_organizacion: '', descripcion: '' };
                this.preview = null;
                this.showStatus('Organización creada exitosamente');
                return;
            }
            const errorData = await response.json().catch(() => ({}));
            alert(errorData.message || 'Error al crear la organización');
        } catch (error) {
            console.error('Error creating organization:', error);
            alert('Error al crear la organización');
        } finally {
            this.isCreatingOrg = false;
        }
    },
    async quickSessionDiagnostics() {
        try {
            const headers = { 'Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') };
            const [whoami, sessionInfo, requestDump] = await Promise.all([
                fetch('/api/whoami', { credentials:'same-origin', headers }).then(r=>r.json().catch(()=>({parse:false}))).catch(()=>({error:true})),
                fetch('/api/debug/session-info', { credentials:'same-origin' }).then(r=>r.json().catch(()=>({parse:false}))).catch(()=>({error:true})),
                fetch('/api/debug/request-dump', { method:'POST', credentials:'same-origin', headers }).then(r=>r.json().catch(()=>({parse:false}))).catch(()=>({error:true}))
            ]);
            console.group('[organization.js] quickSessionDiagnostics');
            console.log('whoami ->', whoami);
            console.log('session-info ->', sessionInfo);
            console.log('request-dump ->', requestDump);
            console.groupEnd();
            return { whoami, sessionInfo, requestDump };
        } catch(e) {
            console.error('[organization.js] quickSessionDiagnostics error', e);
        }
    },
    async debugAuth() {
        try {
            // Ver cookies visibles (solo nombres por seguridad)
            const cookieNames = document.cookie.split(';').map(c=>c.split('=')[0].trim());
            console.log('[organization.js][debugAuth] Cookies presentes:', cookieNames);
            const userResp = await fetch('/api/user', {credentials:'same-origin', headers:{'Accept':'application/json'}});
            const userData = await userResp.json().catch(()=>({}));
            console.log('[organization.js][debugAuth] /api/user status', userResp.status, userData);
        } catch(e) {
            console.error('[organization.js][debugAuth] Error diagnosticando auth', e);
        }
    },
    openGroupModal(org) {
        this.newGroup = { nombre_grupo: '', descripcion: '', id_organizacion: org.id };
        this.currentOrg = org;
        this.showGroupModal = true;
    },
    openEditOrgModal(org) {
        this.selectedOrganization = org;
        this.editForm = {
            nombre_organizacion: org.nombre_organizacion,
            descripcion: org.descripcion || '',
            imagen: org.imagen || ''
        };
        this.showEditModal = true;
    },
    previewEditImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => { this.editPreview = e.target.result; };
            reader.readAsDataURL(file);
        }
    },
    async editOrganization() {
        try {
            const response = await fetch(`/api/organizations/${this.editOrg.id}`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    nombre_organizacion: this.editOrg.nombre_organizacion,
                    descripcion: this.editOrg.descripcion,
                    imagen: this.editPreview
                })
            });
            if (response.status === 419) {
                alert('Sesión expirada. Refresca la página.');
                return;
            }
            if (response.ok) {
                const updated = await response.json();

                // Mostrar mensaje de éxito
                this.showStatus('Organización actualizada exitosamente');

                // Recargar la página después de un breve delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } catch (error) {
            console.error('Error updating organization:', error);
        }
    },
    async deleteOrganization(org) {
        try {
            const response = await fetch(`/api/organizations/${org.id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.status === 419) {
                alert('Sesión expirada. Refresca la página.');
                return;
            }
            if (!response.ok) {
                try {
                    const errorData = await response.json();
                    alert(errorData.message || 'Error al eliminar la organización');
                } catch (parseError) {
                    alert('Error al eliminar la organización');
                }
                return; // Evitar reintentos automáticos
            }

            const idx = this.organizations.findIndex(o => o.id === org.id);
            if (idx !== -1) {
                this.organizations.splice(idx, 1);
            }
        } catch (error) {
            console.error('Error deleting organization:', error);
        }
    },
    openEditGroupModal(org, group) {
        this.selectedGroup = group;
        this.selectedOrganization = org;
        this.editGroupForm = {
            nombre_grupo: group.nombre_grupo,
            descripcion: group.descripcion || ''
        };
        this.showEditGroupModal = true;
    },
    async editGroup() {
        try {
            const response = await fetch(`/api/groups/${this.editGroup.id}`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    nombre_grupo: this.editGroup.nombre_grupo,
                    descripcion: this.editGroup.descripcion
                })
            });
            if (response.status === 419) {
                alert('Sesión expirada. Refresca la página.');
                return;
            }
            if (response.ok) {
                const updated = await response.json();

                // Mostrar mensaje de éxito
                this.showStatus('Grupo actualizado exitosamente');

                // Recargar la página después de un breve delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } catch (error) {
            console.error('Error updating group:', error);
        }
    },
    async deleteGroup(org, group) {
        if (this.isDeletingGroup) return;
        this.isDeletingGroup = true;
        try {
            const response = await fetch(`/api/groups/${group.id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.status === 419) {
                this.showStatus('Sesión expirada. Refresca la página.', 'error');
                return;
            }
            if (!response.ok) {
                let message = 'No se pudo eliminar el grupo';
                try { const data = await response.json(); message = data.message || message; } catch {}
                this.showStatus(message, 'error');
                return;
            }
            // Éxito
            const idx = org.groups.findIndex(g => g.id === group.id);
            if (idx !== -1) {
                org.groups.splice(idx, 1);
            }
            this.showStatus('Grupo eliminado');
            this.closeConfirmDeleteGroup();
        } catch (error) {
            console.error('Error deleting group:', error);
            this.showStatus('Error al eliminar el grupo', 'error');
        } finally {
            this.isDeletingGroup = false;
        }
    },
    async viewGroup(group) {
        console.log('Opening group modal for:', group.nombre_grupo);
        console.log('Current showGroupInfoModal before:', this.showGroupInfoModal);
        console.log('Current isLoadingGroup before:', this.isLoadingGroup);

        // Evitar múltiples clicks
        if (this.isLoadingGroup) {
            console.log('Already loading, returning');
            return;
        }

        this.isLoadingGroup = true;
        this.currentGroup = group;
        this.showGroupInfoModal = true;

        console.log('Set showGroupInfoModal to:', this.showGroupInfoModal);
        console.log('Set isLoadingGroup to:', this.isLoadingGroup);
        console.log('Set currentGroup to:', this.currentGroup.nombre_grupo);

        try {
            // Cargar detalles del grupo con contenedores
            const response = await fetch(`/api/groups/${group.id}`);

            if (response.ok) {
                this.currentGroup = await response.json();
                // Normalize role field for UI checks
                if (!this.currentGroup.user_role && this.currentGroup.current_user_role) {
                    this.currentGroup.user_role = this.currentGroup.current_user_role;
                }
                if (this.currentGroup.organization_is_owner) {
                    // owners can manage containers
                    this.currentGroup.user_role = this.currentGroup.user_role || 'administrador';
                }
                await this.loadGroupContainers(group.id);
                console.log('Group loaded with containers:', this.currentGroup.containers?.length || 0);
            } else {
                throw new Error('Error al cargar el grupo');
            }
        } catch (error) {
            console.error('Error loading group:', error);
            this.currentGroup = group; // Fallback al grupo original
        } finally {
            this.isLoadingGroup = false;
            console.log('Final showGroupInfoModal:', this.showGroupInfoModal);
            console.log('Final isLoadingGroup:', this.isLoadingGroup);
        }
    },

    showStatus(message, type = 'success') {
        this.alertMessage = message;
        this.alertType = type;
        this.showAlert = true;
        clearTimeout(this.alertTimeout);
        this.alertTimeout = setTimeout(() => {
            this.hideStatus();
        }, 3000);
    },
    hideStatus() {
        this.showAlert = false;
        this.alertMessage = '';
    },

    async loadGroupContainers(groupId) {
        try {
            debugLog('Loading containers for group:', groupId);
            const response = await fetch(`/api/groups/${groupId}/containers`);
            if (!response.ok) {
                throw new Error(`Error loading containers: ${response.status}`);
            }
            const data = await response.json();
            // Ensure each container keeps the is_company flag and group name
            this.currentGroup.containers = (data.containers || []).map(c => ({
                ...c,
                is_company: c.is_company ?? true,
                group_name: c.group_name ?? null
            }));
            debugLog('Containers loaded:', this.currentGroup.containers);
        } catch (error) {
            console.error('Error loading group containers:', error);
            this.currentGroup.containers = [];
        }
    },
    async createGroup() {
        if (this.isCreatingGroup) return; // Evitar múltiples clicks

        this.isCreatingGroup = true;
        try {
            const response = await fetch('/api/groups', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    id_organizacion: this.newGroup.id_organizacion,
                    nombre_grupo: this.newGroup.nombre_grupo,
                    descripcion: this.newGroup.descripcion
                })
            });

            if (response.ok) {
                const group = await response.json();

                // Mostrar mensaje de éxito
                this.showStatus('Grupo creado exitosamente');

                // Recargar la página después de un breve delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Error al crear el grupo');
            }
        } catch (error) {
            console.error('Error creating group:', error);
            alert('Error al crear el grupo');
        } finally {
            this.isCreatingGroup = false;
        }
    },
    async checkUserExists() {
        const email = (this.inviteEmail || '').trim();
        const emailRegex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
        if (!emailRegex.test(email)) {
            this.userExists = null;
            this.userExistsMessage = '';
            return;
        }

        try {
            const response = await fetch('/api/users/check-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email })
            });

            if (response.ok) {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    this.userExists = data.exists;
                    this.userExistsMessage = data.exists
                        ? '✓ Este usuario existe en Juntify'
                        : '○ Usuario no registrado, se enviará por email';
                } else {
                    console.error('Server returned non-JSON response');
                    this.userExists = false;
                    this.userExistsMessage = '○ Usuario no registrado, se enviará por email';
                }
            } else {
                console.error('Error checking user, status:', response.status);
                this.userExists = false;
                this.userExistsMessage = '○ Usuario no registrado, se enviará por email';
            }
        } catch (error) {
            console.error('Error checking user:', error);
            this.userExists = false;
            this.userExistsMessage = '○ Usuario no registrado, se enviará por email';
        }
    },
    async sendInvitation() {
        // Prevenir múltiples envíos
        if (this.isSendingInvitation) {
            return;
        }

        if (!this.inviteEmail || !this.inviteEmail.includes('@')) {
            this.showError('Por favor ingresa un email válido');
            return;
        }

        try {
            this.isSendingInvitation = true; // Activar loading

            const response = await fetch(`/api/groups/${this.currentGroup.id}/invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    email: this.inviteEmail,
                    send_notification: this.userExists
                })
            });

            if (response.ok) {
                const data = await response.json();

                if (this.userExists) {
                    this.showSuccess(`Invitación enviada a ${this.inviteEmail}`);
                    if (window.notifications) {
                        window.notifications.refresh();
                    }
                } else {
                    this.showSuccess('Invitación enviada por email correctamente');
                }

                // Limpiar formulario
                this.inviteEmail = '';
                this.userExists = null;
                this.userExistsMessage = '';
                this.showInviteOptions = false;
            } else {
                const errorData = await response.json();
                this.showError(errorData.message || 'Error al enviar la invitación');
            }
        } catch (error) {
            console.error('Error sending invitation:', error);
            this.showError('Error de conexión al enviar la invitación');
        } finally {
            this.isSendingInvitation = false; // Desactivar loading
        }
    },
    async inviteMember(method) {
        // Prevenir múltiples envíos
        if (this.isSendingInvitation) {
            return;
        }

        if (!this.inviteEmail || !this.inviteEmail.includes('@')) {
            this.showError('Por favor ingresa un email válido');
            return;
        }

        try {
            this.isSendingInvitation = true; // Activar loading

            const response = await fetch(`/api/groups/${this.currentGroup.id}/invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    email: this.inviteEmail,
                    method: method
                })
            });

            if (response.ok) {
                this.showSuccess('Invitación enviada correctamente');

                // Limpiar formulario
                this.inviteEmail = '';
                this.showInviteOptions = false;
            } else {
                const errorData = await response.json();
                this.showError(errorData.message || 'Error al enviar la invitación');
            }
        } catch (error) {
            console.error('Error inviting member:', error);
            this.showError('Error de conexión al enviar la invitación');
        } finally {
            this.isSendingInvitation = false; // Desactivar loading
        }
    },
    async joinOrganization() {
        if (!this.inviteCode) {
            alert('Por favor ingresa un código de invitación');
            return;
        }

        if (this.isJoining) return; // Evitar múltiples clicks

        this.isJoining = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const joinRes = await fetch('/api/groups/join-code', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ code: this.inviteCode })
            });

            const data = await joinRes.json().catch(() => ({}));
            if (joinRes.status === 409) {
                this.showError(data.message || 'El usuario ya pertenece a una organización y no puede unirse a otra');
                return;
            }
            if (!joinRes.ok) {
                this.showError(data.message || 'No se pudo unirse al grupo');
                return;
            }

            const { organization, group } = data;
            let existingOrg = this.organizations.find(o => o.id === organization.id);
            if (existingOrg) {
                if (!existingOrg.groups) existingOrg.groups = [];
                if (!existingOrg.groups.some(g => g.id === group.id)) {
                    existingOrg.groups.push(group);
                }
            } else {
                if (!organization.groups) organization.groups = [];
                if (!organization.groups.some(g => g.id === group.id)) {
                    organization.groups.push(group);
                }
                this.organizations.push(organization);
            }

            this.inviteCode = '';

            this.showStatus('Te has unido a la organización exitosamente');
        } catch (error) {
            console.error('Error joining organization:', error);
            alert('Hubo un problema al unirse a la organización');
        } finally {
            this.isJoining = false;
        }
    },
    async leaveOrganization() {
        if (this.isLeaving) return;
        this.isLeaving = true;
        try {
            const orgId = this.currentOrganizationId || (this.organizations[0]?.id);
            const response = await fetch('/api/organizations/leave', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ organization_id: orgId })
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.showError(data.message || 'Error al salir de la organización');
                return;
            }

            if (data.blocked_admin_of && data.blocked_admin_of.length) {
                this.showError('No puedes salir porque administras esa organización');
                return;
            }

            // Actualizar lista local de organizaciones
            if (orgId) {
                this.organizations = this.organizations.filter(o => o.id !== orgId);
            }
            this.currentOrganizationId = data.current_organization_id || null;
            window.location.href = this.currentOrganizationId ? '/organizacion' : '/';
        } catch (error) {
            console.error('Error leaving organization:', error);
            this.showError('Error al salir de la organización');
        } finally {
            this.isLeaving = false;
        }
    },
    async acceptInvitation() {
        try {
            const response = await fetch(`/api/groups/${this.currentGroup.id}/accept`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.ok) {
                const data = await response.json();
                this.currentGroup = data.group;
            }
        } catch (error) {
            console.error('Error joining group:', error);
        }
    },

    // Nuevos métodos para la gestión avanzada

    openInviteModal(group) {
        this.selectedGroup = group;
        this.inviteEmail = '';
        this.inviteRole = 'invitado';
        this.userExists = null;
        this.userExistsMessage = '';
        this.invitableContacts = [];
        this.inviteContactSearch = '';
        this.showInviteModal = true;
        this.loadInvitableContacts();
    },

    async sendGroupInvitation() {
        // Prevenir múltiples envíos
        if (this.isSendingInvitation) {
            return;
        }

        if (!this.selectedGroup || !this.inviteEmail) {
            this.showError('Por favor completa todos los campos requeridos');
            return;
        }

        if (!this.inviteEmail.includes('@')) {
            this.showError('Por favor ingresa un email válido');
            return;
        }

        try {
            this.isSendingInvitation = true; // Activar loading

            const response = await fetch(`/api/groups/${this.selectedGroup.id}/invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    email: this.inviteEmail,
                    send_notification: this.userExists,
                    role: this.inviteRole
                })
            });

            if (response.ok) {
                const data = await response.json();
                this.showSuccess(data.message || 'Invitación enviada correctamente');
                this.showInviteModal = false;

                // Limpiar formulario
                this.inviteEmail = '';
                this.inviteRole = 'invitado';
                this.userExists = null;
                this.userExistsMessage = '';

                // Refrescar los datos del grupo
                await this.refreshGroupData(this.selectedGroup.id);
            } else {
                let errorMessage = 'Error al enviar la invitación';

                try {
                    const errorData = await response.json();
                    if (response.status === 422) {
                        // Error de validación
                        if (errorData.errors) {
                            const errorMessages = Object.values(errorData.errors).flat();
                            errorMessage = errorMessages.join(', ');
                        } else {
                            errorMessage = errorData.message || 'Datos de invitación inválidos';
                        }
                    } else {
                        errorMessage = errorData.message || errorMessage;
                    }
                } catch (parseError) {
                    console.error('Error parsing error response:', parseError);
                    if (response.status === 422) {
                        errorMessage = 'Datos de invitación inválidos';
                    } else if (response.status === 403) {
                        errorMessage = 'No tienes permisos para invitar a este grupo';
                    } else if (response.status === 401) {
                        errorMessage = 'Sesión expirada, recarga la página';
                    } else {
                        errorMessage = `Error del servidor (${response.status})`;
                    }
                }

                this.showError(errorMessage);
            }
        } catch (error) {
            console.error('Error sending group invitation:', error);
            this.showError('Error de conexión al enviar la invitación');
        } finally {
            this.isSendingInvitation = false; // Desactivar loading
        }
    },

    async refreshGroupData(groupId) {
        try {
            const response = await fetch(`/api/groups/${groupId}`);
            if (response.ok) {
                const updatedGroup = await response.json();

                // Actualizar el grupo en la lista de organizaciones
                this.organizations.forEach(org => {
                    if (org.groups) {
                        const groupIndex = org.groups.findIndex(g => g.id === groupId);
                        if (groupIndex !== -1) {
                            org.groups[groupIndex] = updatedGroup;
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Error refreshing group data:', error);
        }
    },

    async updateMemberRole(groupId, user) {
        try {
            const response = await fetch(`/api/groups/${groupId}/members/${user.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ rol: user.pivot.rol })
            });

            if (response.ok) {
                // Mostrar mensaje de éxito
                this.showStatus('Rol actualizado correctamente');
            } else {
                throw new Error('Error al actualizar el rol');
            }
        } catch (error) {
            console.error('Error updating member role:', error);
            this.showError('Error al actualizar el rol del miembro');
        }
    },

    async removeMember() {
        if (!this.memberToRemove || !this.groupIdForMemberRemoval) return;
        if (this.isRemovingMember) return;

        const groupId = this.groupIdForMemberRemoval;
        const userId = this.memberToRemove.id;
        if (!groupId || !userId) {
            console.error('removeMember: invalid IDs', { groupId, userId });
            this.closeConfirmRemoveMember();
            this.showError('Datos inválidos para remover miembro');
            return;
        }

        console.log('removeMember request', { groupId, userId });

        this.isRemovingMember = true;
        try {
            const response = await fetch(`/api/groups/${groupId}/members/${userId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            console.log('removeMember response status', response.status);

            if (response.ok) {
                // Refrescar los datos del grupo
                await this.refreshGroupData(groupId);
                this.closeConfirmRemoveMember();
                this.showGroupInfoModal = false;
                this.showStatus('Miembro removido correctamente');
            } else {
                const data = await response.json().catch(() => ({}));
                if (response.status === 403 || response.status === 500) {
                    console.error('removeMember error', response.status, data);
                }
                this.closeConfirmRemoveMember();
                this.showError(data.message || 'Error al remover el miembro');
            }
        } catch (error) {
            console.error('Error removing member:', error);
            this.closeConfirmRemoveMember();
            this.showError('Error al remover el miembro del grupo');
        } finally {
            this.isRemovingMember = false;
        }
    },

        // Método para editar organización
        editOrganization() {
            this.showEditModal = true;
            this.editForm = {
                nombre_organizacion: this.selectedOrganization.nombre_organizacion,
                descripcion: this.selectedOrganization.descripcion || '',
                imagen: this.selectedOrganization.imagen || '',
                newImagePreview: null,
                newImageFile: null
            };
        },

        // Manejar cambio de imagen
        handleImageChange(event) {
            const file = event.target.files[0];
            if (file) {
                // Validar tipo de archivo
                if (!file.type.startsWith('image/')) {
                    alert('Por favor selecciona un archivo de imagen válido');
                    return;
                }

                // Validar tamaño (5MB máximo)
                if (file.size > 5 * 1024 * 1024) {
                    alert('La imagen es demasiado grande. Máximo 5MB');
                    return;
                }

                this.editForm.newImageFile = file;

                // Crear preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.editForm.newImagePreview = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },

        // Quitar imagen
        removeImage() {
            this.editForm.imagen = '';
            this.editForm.newImagePreview = null;
            this.editForm.newImageFile = null;
            // Limpiar el input de archivo
            const fileInput = document.getElementById('orgImageInput');
            if (fileInput) fileInput.value = '';
        },

        // Función para recargar organizaciones desde el servidor
        async refreshOrganizations() {
            try {
                const response = await fetch('/api/organizations', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();

                    this.organizations = this.filterOrgGroups(data.organizations || []);
                    console.log('Organizaciones recargadas:', this.organizations);

                } else {
                    console.error('Error al recargar organizaciones:', response.status);
                }
            } catch (error) {
                console.error('Error de red al recargar organizaciones:', error);
            }
        },

        // Convertir archivo a base64

        async fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = () => resolve(reader.result);
                reader.onerror = error => reject(error);
            });
        },

        async saveOrganization() {
            // Prevenir múltiples envíos
            if (this.isSavingOrganization) {
                return;
            }
            debugLog('Guardando organización:', this.selectedOrganization.id);
            debugLog('Datos del formulario:', this.editForm);

            this.isSavingOrganization = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            try {

                // Preparar datos para enviar
                let dataToSend = {
                    nombre_organizacion: this.editForm.nombre_organizacion,
                    descripcion: this.editForm.descripcion
                };

                // Si hay una nueva imagen, convertirla a base64
                if (this.editForm.newImageFile) {
                    debugLog('Convirtiendo nueva imagen a base64...');
                    const base64Image = await this.fileToBase64(this.editForm.newImageFile);
                    dataToSend.imagen = base64Image;
                } else if (this.editForm.imagen && this.editForm.imagen !== '') {
                    // Mantener imagen existente
                    dataToSend.imagen = this.editForm.imagen;
                } else {
                    // Sin imagen
                    dataToSend.imagen = null;
                }

                debugLog('Datos a enviar:', dataToSend);

                const response = await fetch(`/api/organizations/${this.selectedOrganization.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(dataToSend)
                });

                debugLog('Respuesta del servidor:', response.status, response.statusText);

                if (response.ok) {
                    const updatedOrg = await response.json();

                    debugLog('Organización actualizada desde servidor:', updatedOrg);

                    // Actualizar la organización seleccionada manteniendo las relaciones existentes
                    this.selectedOrganization.nombre_organizacion = updatedOrg.nombre_organizacion;
                    this.selectedOrganization.descripcion = updatedOrg.descripcion;
                    this.selectedOrganization.imagen = updatedOrg.imagen;

                    // Solo actualizar grupos si vienen en la respuesta, filtrando por usuario
                    if (updatedOrg.groups) {
                        const filtered = this.filterOrgGroups([updatedOrg])[0];
                        this.selectedOrganization.groups = filtered.groups;
                    }

                    this.showEditModal = false;

                    // Limpiar formulario
                    this.editForm.newImagePreview = null;
                    this.editForm.newImageFile = null;

                    // Actualizar en la lista de organizaciones manteniendo los datos existentes
                    const index = this.organizations.findIndex(org => org.id === updatedOrg.id);
                    if (index !== -1) {
                        // Actualizar solo los campos básicos, mantener grupos existentes
                        this.organizations[index].nombre_organizacion = updatedOrg.nombre_organizacion;
                        this.organizations[index].descripcion = updatedOrg.descripcion;
                        this.organizations[index].imagen = updatedOrg.imagen;

                        // Solo actualizar grupos si vienen en la respuesta, filtrando por usuario
                        if (updatedOrg.groups) {
                            const filtered = this.filterOrgGroups([updatedOrg])[0];
                            this.organizations[index].groups = filtered.groups;
                        }
                    }

                    debugLog('Organización actualizada exitosamente');

                    // Mostrar modal de éxito
                    this.showSuccess('Organización actualizada exitosamente');

                    // Recargar datos desde el servidor para asegurar sincronización
                    await this.refreshOrganizations();
                } else {
                    // Manejar diferentes tipos de errores
                    const errorText = await response.text();
                    console.error('Error del servidor:', response.status, errorText);

                    if (response.status === 403) {
                        this.showError('Error: No tienes permisos para editar esta organización. Verifica que seas el propietario.');
                    } else if (response.status === 401) {
                        this.showError('Error: Tu sesión ha expirado. Por favor, recarga la página e inicia sesión nuevamente.');
                    } else if (response.status === 422) {
                        this.showError('Error: Los datos enviados no son válidos. Verifica la información.');
                    } else {
                        this.showError(`Error del servidor (${response.status}): ${response.statusText}`);
                    }
                }
            } catch (error) {
                console.error('Error de red o JavaScript:', error);
                this.showError('Error de conexión. Verifica tu conexión a internet e intenta nuevamente.');
            } finally {
                this.isSavingOrganization = false; // Desactivar loading
            }
    },

        // Método para editar grupo
        editGroupMethod(group) {
            this.selectedGroup = group;
            this.showEditGroupModal = true;
            this.editGroupForm = {
                nombre_grupo: group.nombre_grupo,
                descripcion: group.descripcion || ''
            };
        },

        async saveGroup() {
            // Prevenir múltiples envíos
            if (this.isSavingGroup) {
                return;
            }

            try {
                this.isSavingGroup = true; // Activar loading

                const response = await fetch(`/api/groups/${this.selectedGroup.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.editGroupForm)
                });

                if (response.ok) {
                    const updatedGroup = await response.json();

                    // Mostrar mensaje de éxito
                    this.showStatus('Grupo actualizado exitosamente');

                    // Recargar la página después de un breve delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error('Error al actualizar el grupo');
                }
            } catch (error) {
                console.error('Error updating group:', error);
                this.showError('Error al actualizar el grupo');
            } finally {
                this.isSavingGroup = false; // Desactivar loading
            }
        },

        // Método para cerrar modal de éxito
        closeSuccessModal() {
            this.showSuccessModal = false;
            this.successMessage = '';
            this.isErrorModal = false;
            // Resetear cualquier estado de loading que pueda haber quedado activo
            this.isSavingGroup = false;
            this.isSavingOrganization = false;
            this.isSendingInvitation = false;
            debugLog('Modal de éxito cerrado');
        },

        // Método auxiliar para mostrar modal de éxito con validación
        showSuccess(message) {
            if (message && message.trim() !== '') {
                this.successMessage = message;
                this.isErrorModal = false;
                this.showSuccessModal = true;
                debugLog('Mostrando modal de éxito:', message);
            }
        },

        // Método auxiliar para mostrar modal de error
        showError(message) {
            if (message && message.trim() !== '') {
                this.successMessage = message;
                this.isErrorModal = true;
                this.showSuccessModal = true;
                debugLog('Mostrando modal de error:', message);
            }
        },

    // Funciones para manejar contenedores
    openCreateContainerModal() {
        this.newContainer = { name: '', description: '' };
        this.showCreateContainerModal = true;
    },

    async createContainer() {
        if (this.isCreatingContainer) return;
        if (!this.newContainer.name.trim()) {
            alert('El nombre del contenedor es requerido');
            return;
        }

        this.isCreatingContainer = true;
        try {
            const response = await fetch('/api/containers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    name: this.newContainer.name,
                    description: this.newContainer.description,
                    group_id: this.currentGroup.id
                })
            });

            if (response.ok) {
                const container = await response.json();

                // Agregar el contenedor a la lista del grupo actual conservando is_company
                if (!this.currentGroup.containers) {
                    this.currentGroup.containers = [];
                }
                this.currentGroup.containers.push({
                    ...container.container,
                    is_company: container.container.is_company ?? true,
                    group_name: container.container.group_name ?? null
                });

                this.showCreateContainerModal = false;
                this.newContainer = { name: '', description: '' };

                // Mostrar mensaje de éxito
                this.showStatus('Contenedor creado exitosamente');
            } else {
                let msg = 'Error al crear el contenedor';
                try {
                    const errorData = await response.json();
                    msg = errorData.message || msg;
                } catch {}
                if (response.status === 403) {
                    msg = msg || 'No tienes permisos para crear contenedores en este grupo';
                }
                this.showError(msg);
            }
        } catch (error) {
            console.error('Error creating container:', error);
            alert('Error al crear el contenedor');
        } finally {
            this.isCreatingContainer = false;
        }
    },

    viewContainerMeetings(container) {
        // Abrir modal para ver las reuniones del contenedor
        this.openContainerMeetingsModal(container);
    },

    async openContainerMeetingsModal(container) {
        try {
            // Normalizar estilos del modal por si otra lógica los dejó en hidden/pointer-events:none
            const el = document.getElementById('container-meetings-modal');
            if (el) {
                el.classList.remove('hidden');
                el.removeAttribute('aria-hidden');
                el.style.pointerEvents = '';
                el.style.visibility = '';
                el.style.opacity = '';
            }
            // Mostrar modal inmediatamente con estado de carga visual
            this.selectedContainer = { ...container, meetings: [], _isLoading: true };
            this.showContainerMeetingsModal = true;
            // Cargar las reuniones del contenedor
            const response = await fetch(`/api/content-containers/${container.id}/meetings`);
            if (response.ok) {
                const data = await response.json();
                this.selectedContainer = {
                    ...container,
                    is_company: container.is_company ?? false,
                    group_name: container.group_name ?? null,
                    meetings: data.meetings || [],
                    _isLoading: false
                };
                this.$nextTick(() => {
                    if (typeof attachMeetingEventListeners === 'function') {
                        attachMeetingEventListeners();
                    }

                    // Deshabilitar ver detalles si no hay transcripción
                    this.selectedContainer.meetings.forEach(meeting => {
                        if (!meeting.has_transcript) {
                            const card = document.querySelector(`.meeting-card[data-meeting-id='${meeting.id}']`);
                            if (card) {
                                const message = document.createElement('span');
                                message.className = 'block mt-2 text-xs text-slate-400';
                                message.textContent = 'Transcripción no disponible';
                                const content = card.querySelector('.meeting-content');
                                if (content) {
                                    content.appendChild(message);
                                }

                                card.addEventListener('click', (e) => {
                                    if (e.target.closest('.delete-btn') || e.target.closest('.edit-btn') || e.target.closest('.container-btn') || e.target.closest('.remove-btn') || e.target.closest('.download-btn')) {
                                        return;
                                    }
                                    e.stopImmediatePropagation();
                                    e.preventDefault();
                                    alert('Transcripción no disponible');
                                }, true);
                            }
                        }
                    });
                });
            } else {
                alert('Error al cargar las reuniones del contenedor');
            }
        } catch (error) {
            console.error('Error loading container meetings:', error);
            alert('Error al cargar las reuniones del contenedor');
        }
    },

    init() {
        // Escuchar evento global para reabrir modal de contenedor desde reuniones_v2
        window.addEventListener('juntify:open-container-meetings', (e) => {
            const id = e?.detail?.containerId;
            if (!id) return;
            // Si ya tenemos currentGroup, buscar el contenedor por id
            const container = (this.currentGroup?.containers || []).find(c => String(c.id) === String(id));
            if (container) {
                // Mostrar loading rápido para feedback
                this.selectedContainer = { ...container, meetings: [], _isLoading: true };
                this.showContainerMeetingsModal = true;
                // Abrir con fetch
                this.openContainerMeetingsModal(container);
            } else {
                // Fallback: construir objeto mínimo
                this.selectedContainer = { id, name: 'Contenedor', description: '', meetings: [], _isLoading: true };
                this.showContainerMeetingsModal = true;
                // Intentar cargar igualmente
                this.openContainerMeetingsModal({ id, name: 'Contenedor', description: '' });
            }
        });
    },

    editContainer(container) {
        // Abrir modal de edición con los datos del contenedor
        this.editContainer = {
            id: container.id,
            name: container.name,
            description: container.description || ''
        };
        this.showEditContainerModal = true;
    },

    async saveContainer() {
        if (!this.editContainer.name.trim()) {
            alert('El nombre del contenedor es requerido');
            return;
        }

        try {
            const response = await fetch(`/api/containers/${this.editContainer.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    name: this.editContainer.name,
                    description: this.editContainer.description
                })
            });

            if (response.ok) {
                const result = await response.json();

                // Actualizar el contenedor en la lista
                const index = this.currentGroup.containers.findIndex(c => c.id === this.editContainer.id);
                if (index !== -1) {
                    this.currentGroup.containers[index] = {
                        ...this.currentGroup.containers[index],
                        name: this.editContainer.name,
                        description: this.editContainer.description,
                        is_company: this.currentGroup.containers[index].is_company,
                        group_name: this.currentGroup.containers[index].group_name
                    };
                }

                this.showEditContainerModal = false;

                // Mostrar mensaje de éxito
                this.showStatus('Contenedor actualizado exitosamente');
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Error al actualizar el contenedor');
            }
        } catch (error) {
            console.error('Error updating container:', error);
            alert('Error al actualizar el contenedor');
        }
    },

    openMeetingDownload(meetingId) {
        window.openDownloadModal(meetingId);
    },

    async deleteContainer(container) {
        if (!confirm('¿Estás seguro de que quieres eliminar este contenedor?')) {
            return;
        }

        try {
            const response = await fetch(`/api/containers/${container.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                // Remover el contenedor de la lista
                const index = this.currentGroup.containers.findIndex(c => c.id === container.id);
                if (index !== -1) {
                    this.currentGroup.containers.splice(index, 1);
                }

                // Mostrar mensaje de éxito
                this.showStatus('Contenedor eliminado exitosamente');
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Error al eliminar el contenedor');
            }
        } catch (error) {
            console.error('Error deleting container:', error);
            alert('Error al eliminar el contenedor');
        }
    }
}));

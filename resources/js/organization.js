import Alpine from 'alpinejs';

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
    isJoining: false, // Nueva variable para loading de unirse
    isSavingGroup: false, // Nueva variable para loading de guardar grupo
    isSavingOrganization: false, // Nueva variable para loading de guardar organización
    isSendingInvitation: false, // Nueva variable para loading de enviar invitación
    showSuccessModal: false, // Nueva variable para modal de éxito
    successMessage: '', // Nueva variable para mensaje de éxito
    isErrorModal: false, // Nueva variable para distinguir entre éxito y error
    userId: Number(document.querySelector('meta[name="user-id"]').getAttribute('content')),
    activeTab: 'contenedores', // Cambiar tab por defecto a contenedores
    isOwner: false,

    // Método de inicialización para resetear estados
    init() {
        this.showSuccessModal = false;
        this.successMessage = '';
        this.isErrorModal = false;
        this.isSavingGroup = false;
        this.isSavingOrganization = false;
        this.isSendingInvitation = false;
        this.isCreatingOrg = false;
        this.isCreatingGroup = false;
        this.isCreatingContainer = false;
        this.isJoining = false;
        console.log('Estado de organización reiniciado');
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
        try {
            const response = await fetch('/api/organizations', {
                method: 'POST',
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

            if (response.status === 403) {
                const data = await response.json();
                alert(data.message || 'No puedes crear otra organización');
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

                // Mostrar mensaje de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Organización creada exitosamente';
                document.body.appendChild(notification);
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Error al crear la organización');
            }
        } catch (error) {
            console.error('Error creating organization:', error);
            alert('Error al crear la organización');
        } finally {
            this.isCreatingOrg = false;
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
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    nombre_organizacion: this.editOrg.nombre_organizacion,
                    descripcion: this.editOrg.descripcion,
                    imagen: this.editPreview
                })
            });
            if (response.ok) {
                const updated = await response.json();
                const idx = this.organizations.findIndex(o => o.id === updated.id);
                if (idx !== -1) {
                    updated.groups = this.organizations[idx].groups;
                    updated.imagen = this.editPreview;
                    this.organizations[idx] = updated;
                }
                this.showEditOrgModal = false;
            }
        } catch (error) {
            console.error('Error updating organization:', error);
        }
    },
    async deleteOrganization(org) {
        try {
            const response = await fetch(`/api/organizations/${org.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.ok) {
                const idx = this.organizations.findIndex(o => o.id === org.id);
                if (idx !== -1) {
                    this.organizations.splice(idx, 1);
                }
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
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    nombre_grupo: this.editGroup.nombre_grupo,
                    descripcion: this.editGroup.descripcion
                })
            });
            if (response.ok) {
                const updated = await response.json();
                const idx = this.currentOrg.groups.findIndex(g => g.id === updated.id);
                if (idx !== -1) {
                    this.currentOrg.groups[idx] = updated;
                }
                this.showEditGroupModal = false;
            }
        } catch (error) {
            console.error('Error updating group:', error);
        }
    },
    async deleteGroup(org, group) {
        try {
            const response = await fetch(`/api/groups/${group.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.ok) {
                const idx = org.groups.findIndex(g => g.id === group.id);
                if (idx !== -1) {
                    org.groups.splice(idx, 1);
                }
            }
        } catch (error) {
            console.error('Error deleting group:', error);
        }
    },
    async viewGroup(group) {
        try {
            const response = await fetch(`/api/groups/${group.id}`);
            if (response.ok) {
                this.currentGroup = await response.json();

                // Cargar contenedores del grupo
                await this.loadGroupContainers(group.id);

                this.showGroupInfoModal = true;
                this.showInviteOptions = false;
                this.inviteEmail = '';
                const org = this.organizations.find(o => o.groups && o.groups.some(g => g.id === group.id));
                this.isOwner = org ? org.is_owner : false;
                this.activeTab = 'contenedores'; // Cambiar a contenedores por defecto
            }
        } catch (error) {
            console.error('Error loading group:', error);
        }
    },

    async loadGroupContainers(groupId) {
        try {
            const response = await fetch(`/api/groups/${groupId}/containers`);
            if (response.ok) {
                const data = await response.json();
                this.currentGroup.containers = data.containers || [];
            }
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
                if (!this.currentOrg.groups) {
                    this.currentOrg.groups = [];
                }
                this.currentOrg.groups.push(group);
                this.showGroupModal = false;
                this.newGroup = { nombre_grupo: '', descripcion: '', id_organizacion: null };

                // Mostrar mensaje de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Grupo creado exitosamente';
                document.body.appendChild(notification);
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
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

            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
            notification.textContent = 'Te has unido a la organización exitosamente';
            document.body.appendChild(notification);
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 3000);
        } catch (error) {
            console.error('Error joining organization:', error);
            alert('Hubo un problema al unirse a la organización');
        } finally {
            this.isJoining = false;
        }
    },
    async removeMember(user) {
        try {
            const response = await fetch(`/api/groups/${this.currentGroup.id}/members/${user.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            if (response.ok) {
                this.currentGroup.users = this.currentGroup.users.filter(u => u.id !== user.id);
                this.currentGroup.miembros--;
                const org = this.organizations.find(o => o.groups && o.groups.some(g => g.id === this.currentGroup.id));
                if (org) {
                    const g = org.groups.find(g => g.id === this.currentGroup.id);
                    if (g) {
                        g.miembros = this.currentGroup.miembros;
                    }
                }
            }
        } catch (error) {
            console.error('Error removing member:', error);
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
        this.showInviteModal = true;
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
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Rol actualizado correctamente';
                document.body.appendChild(notification);

                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
            } else {
                throw new Error('Error al actualizar el rol');
            }
        } catch (error) {
            console.error('Error updating member role:', error);
            alert('Error al actualizar el rol del miembro');
        }
    },

    async removeMember(groupId, user) {
        if (!confirm(`¿Estás seguro de que quieres quitar a ${user.full_name} del grupo?`)) {
            return;
        }

        try {
            const response = await fetch(`/api/groups/${groupId}/members/${user.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                // Refrescar los datos del grupo
                await this.refreshGroupData(groupId);

                // Mostrar mensaje de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Miembro removido correctamente';
                document.body.appendChild(notification);

                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
            } else {
                throw new Error('Error al remover el miembro');
            }
        } catch (error) {
            console.error('Error removing member:', error);
            alert('Error al remover el miembro del grupo');
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
                    this.organizations = data.organizations || [];
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

            try {
                this.isSavingOrganization = true; // Activar loading

                // Verificar token CSRF
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    this.showError('Error: Token de seguridad no encontrado. Recarga la página.');
                    return;
                }

                console.log('Guardando organización:', this.selectedOrganization.id);
                console.log('Datos del formulario:', this.editForm);

                // Preparar datos para enviar
                let dataToSend = {
                    nombre_organizacion: this.editForm.nombre_organizacion,
                    descripcion: this.editForm.descripcion
                };

                // Si hay una nueva imagen, convertirla a base64
                if (this.editForm.newImageFile) {
                    console.log('Convirtiendo nueva imagen a base64...');
                    const base64Image = await this.fileToBase64(this.editForm.newImageFile);
                    dataToSend.imagen = base64Image;
                } else if (this.editForm.imagen && this.editForm.imagen !== '') {
                    // Mantener imagen existente
                    dataToSend.imagen = this.editForm.imagen;
                } else {
                    // Sin imagen
                    dataToSend.imagen = null;
                }

                console.log('Datos a enviar:', dataToSend);

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

                console.log('Respuesta del servidor:', response.status, response.statusText);

                if (response.ok) {
                    const updatedOrg = await response.json();

                    console.log('Organización actualizada desde servidor:', updatedOrg);

                    // Actualizar la organización seleccionada manteniendo las relaciones existentes
                    this.selectedOrganization.nombre_organizacion = updatedOrg.nombre_organizacion;
                    this.selectedOrganization.descripcion = updatedOrg.descripcion;
                    this.selectedOrganization.imagen = updatedOrg.imagen;

                    // Solo actualizar grupos si vienen en la respuesta, sino mantener los existentes
                    if (updatedOrg.groups) {
                        this.selectedOrganization.groups = updatedOrg.groups;
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

                        // Solo actualizar grupos si vienen en la respuesta
                        if (updatedOrg.groups) {
                            this.organizations[index].groups = updatedOrg.groups;
                        }
                    }

                    console.log('Organización actualizada exitosamente');

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
                    this.showEditGroupModal = false;

                    // Actualizar en la lista de grupos
                    const index = this.selectedOrganization.groups.findIndex(g => g.id === updatedGroup.id);
                    if (index !== -1) {
                        this.selectedOrganization.groups[index] = updatedGroup;
                    }

                    // Mostrar modal de éxito
                    this.showSuccess('Grupo actualizado correctamente');
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
            console.log('Modal de éxito cerrado');
        },

        // Método auxiliar para mostrar modal de éxito con validación
        showSuccess(message) {
            if (message && message.trim() !== '') {
                this.successMessage = message;
                this.isErrorModal = false;
                this.showSuccessModal = true;
                console.log('Mostrando modal de éxito:', message);
            }
        },

        // Método auxiliar para mostrar modal de error
        showError(message) {
            if (message && message.trim() !== '') {
                this.successMessage = message;
                this.isErrorModal = true;
                this.showSuccessModal = true;
                console.log('Mostrando modal de error:', message);
            }
        },

    // Método para salirse de la organización
    async leaveOrganization() {
        if (!confirm('¿Estás seguro de que quieres salir de esta organización?')) {
            return;
        }

        try {
            const response = await fetch('/api/organizations/leave', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                // Recargar la página para mostrar la vista de unirse/crear
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.message || 'Error al salir de la organización');
            }
        } catch (error) {
            console.error('Error leaving organization:', error);
            alert('Error al salir de la organización');
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

                // Agregar el contenedor a la lista del grupo actual
                if (!this.currentGroup.containers) {
                    this.currentGroup.containers = [];
                }
                this.currentGroup.containers.push(container.container);

                this.showCreateContainerModal = false;
                this.newContainer = { name: '', description: '' };

                // Mostrar mensaje de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Contenedor creado exitosamente';
                document.body.appendChild(notification);
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Error al crear el contenedor');
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
            // Cargar las reuniones del contenedor
            const response = await fetch(`/api/content-containers/${container.id}/meetings`);
            if (response.ok) {
                const data = await response.json();
                this.selectedContainer = {
                    ...container,
                    meetings: data.meetings || []
                };
                this.showContainerMeetingsModal = true;
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
                        description: this.editContainer.description
                    };
                }

                this.showEditContainerModal = false;

                // Mostrar mensaje de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Contenedor actualizado exitosamente';
                document.body.appendChild(notification);
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
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
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-3 rounded-lg shadow-lg z-50';
                notification.textContent = 'Contenedor eliminado exitosamente';
                document.body.appendChild(notification);
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
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

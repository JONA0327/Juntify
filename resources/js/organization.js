import Alpine from 'alpinejs';

Alpine.data('organizationPage', (initialOrganizations = []) => ({
    organizations: initialOrganizations,
    showOrgModal: false,
    showGroupModal: false,
    showGroupInfoModal: false,
    showInviteOptions: false,
    showEditOrgModal: false,
    showEditGroupModal: false,
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
    editGroup: {
        id: null,
        nombre_grupo: '',
        descripcion: '',
        id_organizacion: null
    },
    currentOrg: null,
    currentGroup: null,
    inviteEmail: '',
    inviteCode: '',
    userExists: null,
    userExistsMessage: '',
    userId: Number(document.querySelector('meta[name="user-id"]').getAttribute('content')),

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
        try {
            const response = await fetch('/api/organizations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
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
            }
        } catch (error) {
            console.error('Error creating organization:', error);
        }
    },
    openGroupModal(org) {
        this.newGroup = { nombre_grupo: '', descripcion: '', id_organizacion: org.id };
        this.currentOrg = org;
        this.showGroupModal = true;
    },
    openEditOrgModal(org) {
        this.editOrg = { id: org.id, nombre_organizacion: org.nombre_organizacion, descripcion: org.descripcion };
        this.editPreview = org.imagen || null;
        this.showEditOrgModal = true;
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
        this.editGroup = { id: group.id, nombre_grupo: group.nombre_grupo, descripcion: group.descripcion, id_organizacion: org.id };
        this.currentOrg = org;
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
                this.showGroupInfoModal = true;
                this.showInviteOptions = false;
                this.inviteEmail = '';
            }
        } catch (error) {
            console.error('Error loading group:', error);
        }
    },
    async createGroup() {
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
            }
        } catch (error) {
            console.error('Error creating group:', error);
        }
    },
    async checkUserExists() {
        if (!this.inviteEmail || !this.inviteEmail.includes('@')) {
            this.userExists = null;
            this.userExistsMessage = '';
            return;
        }

        try {
            const response = await fetch('/api/users/check-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    email: this.inviteEmail
                })
            });

            if (response.ok) {
                const data = await response.json();
                this.userExists = data.exists;
                this.userExistsMessage = data.exists
                    ? '✓ Este usuario existe en Juntify'
                    : '○ Usuario no registrado, se enviará por email';
            }
        } catch (error) {
            console.error('Error checking user:', error);
            this.userExists = null;
            this.userExistsMessage = '';
        }
    },
    async sendInvitation() {
        if (!this.inviteEmail) {
            alert('Por favor ingresa un email');
            return;
        }

        try {
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
                    alert(`Invitación enviada a ${this.inviteEmail}`);
                    if (window.notifications) {
                        window.notifications.refresh();
                    }
                } else {
                    alert('Invitación enviada por email correctamente');
                }

                this.inviteEmail = '';
                this.userExists = null;
                this.userExistsMessage = '';
                this.showInviteOptions = false;
            }
        } catch (error) {
            console.error('Error sending invitation:', error);
        }
    },
    async inviteMember(method) {
        try {
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
                this.inviteEmail = '';
                this.showInviteOptions = false;
            }
        } catch (error) {
            console.error('Error inviting member:', error);
        }
    },
    async joinOrganization() {
        if (!this.inviteCode) {
            alert('Por favor ingresa un código de invitación');
            return;
        }

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const joinRes = await fetch(`/api/organizations/${this.inviteCode}/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                }
            });

            if (!joinRes.ok) {
                const data = await joinRes.json().catch(() => ({}));
                alert(data.message || 'No se pudo unirse a la organización');
                return;
            }

            const orgRes = await fetch(`/api/organizations/${this.inviteCode}`);
            if (orgRes.ok) {
                const org = await orgRes.json();
                if (!this.organizations.some(o => o.id === org.id)) {
                    if (!org.groups) {
                        org.groups = [];
                    }
                    this.organizations.push(org);
                }
            }

            this.inviteCode = '';
        } catch (error) {
            console.error('Error joining organization:', error);
            alert('Hubo un problema al unirse a la organización');
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
    }
}));

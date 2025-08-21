import Alpine from 'alpinejs';

Alpine.data('organizationPage', (initialOrganizations = []) => ({
    organizations: initialOrganizations,
    showOrgModal: false,
    showGroupModal: false,
    showGroupInfoModal: false,
    showInviteOptions: false,
    newOrg: {
        nombre_organizacion: '',
        descripcion: ''
    },
    preview: null,
    newGroup: {
        nombre_grupo: '',
        descripcion: '',
        id_organizacion: null
    },
    currentOrg: null,
    currentGroup: null,
    inviteEmail: '',
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

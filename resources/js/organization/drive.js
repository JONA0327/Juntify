window.orgDrivePage = function orgDrivePage({ orgId }) {
    return {
        orgId,
        connected: false,
        rootFolder: null,
        subfolders: [],
        newSubfolder: '',
        connectUrl: '/auth/google/redirect?from=organization&return=' + encodeURIComponent(window.location.pathname),
        async init() {
            await this.refresh();
        },
        async refresh() {
            try {
                const res = await fetch(`/api/organizations/${this.orgId}/drive/status`, { credentials: 'include' });
                const data = await res.json();
                this.connected = !!data.connected;
                this.rootFolder = data.root_folder || null;
                this.subfolders = data.subfolders || [];
            } catch (e) { console.error(e); }
        },
        async createRoot() {
            try {
                const res = await fetch(`/api/organizations/${this.orgId}/drive/root-folder`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    credentials: 'include'
                });
                if (res.ok) { await this.refresh(); }
            } catch (e) { console.error(e); }
        },
        async createSubfolder() {
            if (!this.newSubfolder.trim()) return;
            try {
                const res = await fetch(`/api/organizations/${this.orgId}/drive/subfolders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ name: this.newSubfolder }),
                    credentials: 'include'
                });
                if (res.ok) {
                    this.newSubfolder = '';
                    await this.refresh();
                }
            } catch (e) { console.error(e); }
        }
    };
};

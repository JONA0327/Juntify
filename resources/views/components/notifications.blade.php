<div class="notifications" id="notifications-container">
    <button type="button" class="notifications-toggle" id="notif-btn" aria-label="Abrir notificaciones" title="Notificaciones">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        <span class="notifications-dot hidden" aria-hidden="true"></span>
    </button>

    <div class="notifications-panel hidden" id="notif-panel">
        <div class="notifications-header">Notificaciones</div>
        <ul class="notifications-list"></ul>
    </div>
</div>

<script>
(function() {
    const btn = document.getElementById('notif-btn');
    const panel = document.getElementById('notif-panel');
    
    if (!btn || !panel) return;
    
    // Toggle al hacer clic en el botón
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        panel.classList.toggle('hidden');
    });
    
    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (panel.classList.contains('hidden')) return;
        
        const container = document.getElementById('notifications-container');
        if (!container.contains(e.target)) {
            panel.classList.add('hidden');
        }
    });
})();
</script>

<style>
/* Panel styling */
.notifications-panel.notifications-panel--styled {
    background: rgba(15,23,42,0.92); /* slate-900 with opacity */
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(100,116,139,0.25); /* slate-500/25 */
    border-radius: 0.75rem; /* rounded-xl */
    padding: 0.75rem 0.75rem 0.5rem 0.75rem;
    box-shadow: 0 8px 24px -4px rgba(0,0,0,0.4), 0 2px 6px -1px rgba(0,0,0,0.3);
    max-height: 70vh;
    overflow-y: auto;
}
.notifications-panel.notifications-panel--styled .notifications-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: #f1f5f9; /* slate-100 */
    padding: 0 0.25rem 0.5rem 0.25rem;
    margin-bottom: 0.25rem;
    border-bottom: 1px solid rgba(100,116,139,0.25);
}
.notifications-panel.notifications-panel--styled .notifications-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
/* Hide scrollbar (Firefox) */
.notifications-panel.notifications-panel--styled { scrollbar-width: none; }
/* Hide scrollbar (WebKit) */
.notifications-panel.notifications-panel--styled::-webkit-scrollbar { width: 0; height: 0; }
/* Smooth scrolling */
.notifications-panel.notifications-panel--styled { scroll-behavior: smooth; }

/* Scrollbar still accessible for accessibility if user forces it */
@media (prefers-reduced-transparency: reduce) {
  .notifications-panel.notifications-panel--styled {
    backdrop-filter: none; -webkit-backdrop-filter: none;
  }
}
</style>

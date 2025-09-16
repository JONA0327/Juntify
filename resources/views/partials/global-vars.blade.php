<script>
// Global variables injection (shared partial)
// Safely sets window.userRole and window.currentOrganizationId if not already defined.
(function(){
    try {
        if (typeof window.userRole === 'undefined' || window.userRole === null) {
            @if(isset($user))
                window.userRole = @json($user->roles ?? null);
            @else
                window.userRole = window.userRole || null;
            @endif
        }
        if (typeof window.currentOrganizationId === 'undefined' || window.currentOrganizationId === null) {
            @if(isset($organizationId))
                window.currentOrganizationId = @json($organizationId);
            @elseif(isset($organizations) && count($organizations) > 0)
                // Pick first organization id as a fallback (used on organization page)
                window.currentOrganizationId = @json(optional($organizations->first())->id);
            @else
                window.currentOrganizationId = window.currentOrganizationId || null;
            @endif
        }
        // Also expose user id for convenience
        if (typeof window.authUserId === 'undefined') {
            @if(auth()->check())
                window.authUserId = @json(auth()->id());
            @else
                window.authUserId = null;
            @endif
        }
        // Provide dataset fallbacks for scripts that look at body dataset
        if (document && document.body) {
            if (!document.body.dataset.userRole && window.userRole) {
                document.body.dataset.userRole = window.userRole;
            }
            if (!document.body.dataset.organizationId && window.currentOrganizationId) {
                document.body.dataset.organizationId = window.currentOrganizationId;
            }
        }
        console.debug('[global-vars partial] userRole=', window.userRole, 'currentOrganizationId=', window.currentOrganizationId);
    } catch(e) {
        console.error('[global-vars partial] Error injecting globals', e);
    }
})();
</script>

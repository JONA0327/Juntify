function confirmResponse(action) {
    const reason = action === 'reject' ? document.getElementById('rejectReason')?.value || '' : '';
    const url = new URL(window.location.href);

    if (reason) {
        url.searchParams.set('reason', reason);
    }

    window.location.href = url.toString();
}

window.confirmResponse = confirmResponse;

const endpointsMeta = document.querySelector('meta[name="task-endpoints"]');
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

window.taskLaravel = {
    apiMeetings: endpointsMeta.dataset.apiMeetings,
    apiImport: (id) => `${endpointsMeta.dataset.apiImport}${id}`,
    apiExists: endpointsMeta.dataset.apiExists,
    apiTasks: endpointsMeta.dataset.apiTasks,
    csrf: csrfToken,
};

window.taskData = {
    apiTasks: endpointsMeta.dataset.apiCalendar,
};

window.showTasksPanel = function(show = true) {
    const panel = document.getElementById('tasks-panel');
    const empty = document.getElementById('tasks-empty');
    if (show) {
        panel.classList.remove('hidden');
        empty.classList.add('hidden');
    } else {
        panel.classList.add('hidden');
        empty.classList.remove('hidden');
    }
};

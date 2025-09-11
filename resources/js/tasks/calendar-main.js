// Configuraciones y utilidades para el calendario principal de tareas
window.taskLaravel = {
  apiMeetings: '/api/tasks-laravel/meetings',
  apiImport: id => `/api/tasks-laravel/import/${id}`,
  apiExists: '/api/tasks-laravel/exists',
  apiTasks: '/api/tasks-laravel/tasks',
  csrf: document.querySelector('meta[name="csrf-token"]')?.content || ''
};

window.taskData = {
  apiTasks: '/api/tasks-laravel/calendar'
};

window.showTasksPanel = function (show = true) {
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

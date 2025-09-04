// Global redirect to login on session expiration for both axios and fetch
(() => {
  let redirecting = false;
  const redirectToLogin = () => {
  // Avoid redirect loops if we're already on the login page
    if (redirecting || window.location.pathname.startsWith('/login')) return;
    redirecting = true;
    const current = window.location.pathname + window.location.search;
    const loginUrl = '/login?redirect=' + encodeURIComponent(current);
    try {
      window.location.href = loginUrl;
    } catch (_) {
      // Fallback
      window.location.assign('/login');
    }
  };

  // Axios interceptor
  if (window.axios && typeof window.axios.interceptors?.response?.use === 'function') {
    // Ensure cookies are sent on same-origin requests
    window.axios.defaults.withCredentials = true;
    window.axios.interceptors.response.use(
      (response) => response,
      (error) => {
        const status = error?.response?.status;
        if (status === 401 || status === 419) {
          redirectToLogin();
          // Return a pending promise to stop further error handling
          return new Promise(() => {});
        }
        return Promise.reject(error);
      }
    );
  }

  // Fetch wrapper
  if (typeof window.fetch === 'function') {
    const originalFetch = window.fetch.bind(window);
    window.fetch = async (input, init = {}) => {
  const url = typeof input === 'string'
    ? input
    : (input?.url || input?.href || '');
  const isSameOrigin = url.startsWith('/') || url.startsWith(window.location.origin);

  // Prepare options & headers without mutating caller's object
  const opts = { ...init };
  const method = (opts.method || 'GET').toUpperCase();

  // Ensure cookies are sent for same-origin requests so sessions work
  if (!opts.credentials) {
    opts.credentials = isSameOrigin ? 'same-origin' : (opts.credentials || 'omit');
  }

  // Normalize headers to a plain object
  const origHeaders = opts.headers || {};
  const headers = (origHeaders instanceof Headers)
    ? Object.fromEntries(origHeaders.entries())
    : { ...origHeaders };

  // Mark as AJAX so Laravel returns 401 instead of redirecting HTML
  if (!headers['X-Requested-With']) headers['X-Requested-With'] = 'XMLHttpRequest';

  // Attach CSRF token to non-GET methods on same-origin requests
  const needsCsrf = isSameOrigin && !['GET', 'HEAD', 'OPTIONS'].includes(method);
  if (needsCsrf && !headers['X-CSRF-TOKEN']) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) headers['X-CSRF-TOKEN'] = token;
  }

  opts.headers = headers;

  const res = await originalFetch(input, opts);
      if (res && (res.status === 401 || res.status === 419)) {
        redirectToLogin();
      }
      return res;
    };
  }
})();

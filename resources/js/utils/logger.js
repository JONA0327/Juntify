const resolveDebugFlag = () => {
    if (typeof window !== 'undefined') {
        if (typeof window.APP_DEBUG !== 'undefined') {
            return Boolean(window.APP_DEBUG);
        }
        const doc = window.document;
        if (doc) {
            const fromDataset = doc.documentElement?.dataset?.appDebug ?? doc.body?.dataset?.appDebug;
            if (typeof fromDataset !== 'undefined') {
                return fromDataset === 'true' || fromDataset === true;
            }
        }
    }

    if (typeof import.meta !== 'undefined' && import.meta.env) {
        if (typeof import.meta.env.VITE_APP_DEBUG !== 'undefined') {
            return import.meta.env.VITE_APP_DEBUG === 'true' || import.meta.env.VITE_APP_DEBUG === true;
        }
        if (typeof import.meta.env.APP_DEBUG !== 'undefined') {
            return import.meta.env.APP_DEBUG === 'true' || import.meta.env.APP_DEBUG === true;
        }
        if (typeof import.meta.env.DEV !== 'undefined') {
            return Boolean(import.meta.env.DEV);
        }
    }

    return false;
};

let overrideDebug = null;

const shouldLog = () => {
    if (overrideDebug !== null) {
        return overrideDebug;
    }
    return resolveDebugFlag();
};

const callConsole = (method, args) => {
    if (!shouldLog()) {
        return;
    }
    if (typeof console === 'undefined') {
        return;
    }
    const fn = console[method];
    if (typeof fn === 'function') {
        fn.apply(console, args);
    }
};

export const debugLog = (...args) => callConsole('log', args);
export const debugInfo = (...args) => callConsole('info', args);
export const debugWarn = (...args) => callConsole('warn', args);
export const debugError = (...args) => callConsole('error', args);
export const debugDebug = (...args) => callConsole('debug', args);
export const debugGroup = (...args) => callConsole('group', args);
export const debugGroupCollapsed = (...args) => callConsole('groupCollapsed', args);
export const debugGroupEnd = (...args) => callConsole('groupEnd', args);
export const debugTable = (...args) => callConsole('table', args);

export const isDebugEnabled = () => shouldLog();
export const setLoggerDebug = (value) => {
    overrideDebug = value === null ? null : Boolean(value);
};

const loggerApi = {
    debugLog,
    debugInfo,
    debugWarn,
    debugError,
    debugDebug,
    debugGroup,
    debugGroupCollapsed,
    debugGroupEnd,
    debugTable,
    isDebugEnabled,
    setLoggerDebug,
};

if (typeof window !== 'undefined') {
    if (typeof window.APP_DEBUG === 'undefined') {
        const computed = resolveDebugFlag();
        window.APP_DEBUG = computed;
    }
    window.juntifyLogger = Object.assign({}, window.juntifyLogger || {}, loggerApi);
}

export default loggerApi;

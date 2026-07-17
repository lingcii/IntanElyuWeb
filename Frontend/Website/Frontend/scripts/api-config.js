(function () {
    /**
     * api-config.js
     * Central configuration for all API calls.
     *
     * All JS scripts should import/use API_CONFIG instead of
     * hardcoding the backend URL so that switching backends
     * (dev → production) only requires changing this file.
     *
     * IMPORTANT: Never hardcode a host (e.g. 127.0.0.1 or localhost) anywhere
     * else in the codebase — always go through window.API_CONFIG.* below.
     * Mixing hosts breaks the session cookie because browsers treat
     * 127.0.0.1 and localhost as different origins, even on the same machine.
     */

    // ── Top-level guard ──────────────────────────────────────────────────────────
    // This exits immediately if the script was already loaded to prevent duplicate logic.
    if (window.__API_CONFIG_LOADED__) {
        void 0;
        return;
    }
    window.__API_CONFIG_LOADED__ = true;

    // Always derive the host from the CURRENT page location.
    // This guarantees API calls always match whatever host/port the
    // browser actually used to load the page, so the session cookie
    // (scoped to that same host) is always sent.
    const apiHost = window.location.hostname || '127.0.0.1';
    const apiPort = '8000';
    const baseUrl = `${window.location.protocol}//${apiHost}:${apiPort}`;

    // Dynamically calculate the frontend base URL to support both XAMPP subfolders and php -S
    let frontendBaseUrl = '';
    
    // 1. Try to derive from the current page URL (highly reliable since our directory structure is known)
    const href = window.location.href;
    const lowerHref = href.toLowerCase();
    
    if (lowerHref.includes('/views/')) {
        const idx = lowerHref.indexOf('/views/');
        frontendBaseUrl = href.substring(0, idx + 1); // include the trailing slash
    } else if (lowerHref.endsWith('login.php') || lowerHref.includes('login.php?')) {
        const idx = lowerHref.indexOf('login.php');
        frontendBaseUrl = href.substring(0, idx);
    } else if (lowerHref.endsWith('index.php') || lowerHref.includes('index.php?')) {
        const idx = lowerHref.indexOf('index.php');
        frontendBaseUrl = href.substring(0, idx);
    }
    
    // 2. Fallback: try document.currentScript
    if (!frontendBaseUrl && document.currentScript && document.currentScript.src) {
        const scriptSrc = document.currentScript.src;
        const idx = scriptSrc.toLowerCase().indexOf('scripts/api-config.js');
        if (idx !== -1) {
            frontendBaseUrl = scriptSrc.substring(0, idx);
        }
    }
    
    // 3. Last fallback: origin
    if (!frontendBaseUrl) {
        frontendBaseUrl = window.location.origin + '/';
    }

    // Recursive helper to normalize any photo_url keys in API responses
    function normalizeAllUrls(obj) {
        if (!obj || typeof obj !== 'object') return obj;

        if (Array.isArray(obj)) {
            for (let i = 0; i < obj.length; i++) {
                obj[i] = normalizeAllUrls(obj[i]);
            }
            return obj;
        }

        for (const key in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, key)) {
                if (key === 'photo_url' && typeof obj[key] === 'string') {
                    obj[key] = window.API_CONFIG.normalizeImageUrl(obj[key]);
                } else {
                    obj[key] = normalizeAllUrls(obj[key]);
                }
            }
        }
        return obj;
    }

    window.API_CONFIG = {
        BASE_URL: baseUrl,
        FRONTEND_BASE_URL: frontendBaseUrl,
        activeRequests: {},

        // Role-scoped base paths
        PITCO: `${baseUrl}/api/pitco`,
        PICTO: `${baseUrl}/api/pitco`,
        LUPTO: `${baseUrl}/api/lupto`,
        MUNICIPAL: `${baseUrl}/api/municipal`,
        AUTH: `${baseUrl}/api/auth`,
        PROFILE: `${baseUrl}/api/profile`,

        /**
         * Converts relative proxy URLs (e.g. /api/serve-image.php?file=...) into
         * absolute URLs relative to the frontend domain and path.
         */
        normalizeImageUrl(url) {
            if (!url) return '';
            if (/^https?:\/\//i.test(url)) {
                return url;
            }
            if (url.startsWith('/api/serve-image.php')) {
                return this.FRONTEND_BASE_URL + url.substring(1);
            }
            if (url.startsWith('api/serve-image.php')) {
                return this.FRONTEND_BASE_URL + url;
            }
            return url;
        },

        /**
         * Returns the XSRF token from the cookie set by Laravel.
         * Required for POST/PUT/DELETE requests even when using session-based auth.
         */
        getCsrfToken() {
            const match = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='));
            if (match) return decodeURIComponent(match.trim().split('=').slice(1).join('='));
            // Fallback: meta tag
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async _executeFetch(url, options = {}) {
            // Guard against accidental hardcoded hosts slipping through
            // from other parts of the codebase — warn loudly in dev.
            if (/^https?:\/\/(127\.0\.0\.1|localhost)/i.test(url) && !url.startsWith(baseUrl)) {
                void 0;
            }

            const method = (options.method || 'GET').toUpperCase();
            const defaults = {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    ...(options.headers || {}),
                },
            };
            // Content-Type is only needed when sending a request body (e.g. POST, PUT, PATCH)
            if (method !== 'GET' && method !== 'HEAD' && !defaults.headers['Content-Type']) {
                defaults.headers['Content-Type'] = 'application/json';
            }
            const mergedOptions = { ...defaults, ...options, headers: defaults.headers };

            let response;
            try {
                response = await window.fetch(url, mergedOptions);
            } catch (networkError) {
                if (networkError.name === 'AbortError') {
                    throw networkError;
                }
                throw new Error('Network error: cannot reach the server. Is Laravel running on port 8000?');
            }

            const text = await response.text();
            if (!text.trim()) {
                if (response.status === 304) {
                    return null;
                }
                throw new Error(`Empty response (HTTP ${response.status})`);
            }

            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error(`Non-JSON response (HTTP ${response.status}): ${text.slice(0, 200)}`);
            }

            if (!response.ok) {
                if (response.status === 401) {
                    void 0;
                    let loginRedirect = 'login.php';
                    const path = window.location.pathname;
                    if (path.includes('/views/PICTO/') || path.includes('/views/LUPTO/') || path.includes('/views/MUNICIPAL/')) {
                        loginRedirect = '../../login.php';
                    }
                    window.location.href = loginRedirect;
                    return new Promise(() => { }); // Halt further Javascript processing
                }
                throw new Error(data.error || data.message || `HTTP ${response.status}`);
            }

            return normalizeAllUrls(data);
        },

        async fetch(url, options = {}) {
            const method = (options.method || 'GET').toUpperCase();

            // Write requests (POST, PUT, DELETE, PATCH) invalidate all active GET requests
            if (method !== 'GET' && method !== 'HEAD') {
                for (const key of Object.keys(this.activeRequests)) {
                    try {
                        this.activeRequests[key].controller.abort();
                    } catch (e) {}
                    delete this.activeRequests[key];
                }
                return this._executeFetch(url, options);
            }

            const cacheKey = url;
            const externalSignal = options.signal;

            if (externalSignal && externalSignal.aborted) {
                throw new DOMException('The user aborted a request.', 'AbortError');
            }

            // Coalesce matching GET requests
            if (this.activeRequests[cacheKey]) {
                const entry = this.activeRequests[cacheKey];
                entry.refCount++;

                let abortHandler;
                if (externalSignal) {
                    abortHandler = () => {
                        entry.refCount--;
                        if (entry.refCount === 0) {
                            entry.controller.abort();
                        }
                    };
                    externalSignal.addEventListener('abort', abortHandler);
                }

                try {
                    return await entry.promise;
                } finally {
                    if (externalSignal && abortHandler) {
                        externalSignal.removeEventListener('abort', abortHandler);
                    }
                }
            }

            // Initiate a new request
            const controller = new AbortController();
            const entry = {
                controller,
                refCount: 1,
                promise: null
            };
            this.activeRequests[cacheKey] = entry;

            let abortHandler;
            if (externalSignal) {
                abortHandler = () => {
                    entry.refCount--;
                    if (entry.refCount === 0) {
                        controller.abort();
                    }
                };
                externalSignal.addEventListener('abort', abortHandler);
            }

            const fetchOptions = { ...options, signal: controller.signal };
            entry.promise = this._executeFetch(url, fetchOptions);

            try {
                return await entry.promise;
            } finally {
                if (externalSignal && abortHandler) {
                    externalSignal.removeEventListener('abort', abortHandler);
                }
                if (this.activeRequests[cacheKey] === entry) {
                    delete this.activeRequests[cacheKey];
                }
            }
        },

        /** Convenience: GET request */
        get(url, params = {}) {
            const { signal, ...qsParams } = params || {};
            const qs = Object.keys(qsParams).length
                ? '?' + new URLSearchParams(qsParams).toString()
                : '';
            return this.fetch(url + qs, { method: 'GET', signal });
        },

        /** Convenience: POST request */
        post(url, body = {}) {
            return this.fetch(url, { method: 'POST', body: JSON.stringify(body) });
        },

        /** Convenience: PUT request */
        put(url, body = {}) {
            return this.fetch(url, { method: 'PUT', body: JSON.stringify(body) });
        },

        /** Convenience: PATCH request */
        patch(url, body = {}) {
            return this.fetch(url, { method: 'PATCH', body: JSON.stringify(body) });
        },

        /** Convenience: DELETE request */
        delete(url) {
            return this.fetch(url, { method: 'DELETE' });
        },
    };
})();
(function () {
    'use strict';

    // Check if we are in one of the views directories
    const path = window.location.pathname;
    if (!path.includes('/views/PICTO/') && !path.includes('/views/LUPTO/') && !path.includes('/views/MUNICIPAL/')) {
        return; // Only run inside views/PICTO, views/LUPTO, or views/MUNICIPAL
    }

    // Determine current page filename (e.g., 'dashboard.php')
    function getPageName(url) {
        try {
            const urlObj = new URL(url, window.location.origin);
            const parts = urlObj.pathname.split('/');
            return parts[parts.length - 1] || 'dashboard.php';
        } catch (e) {
            return 'dashboard.php';
        }
    }

    const initialPage = getPageName(window.location.href);
    const loadedTabs = {};
    let activeTabName = initialPage;

    // Track scroll positions for each tab
    const tabScrollStates = {};

    // Key prefix for sessionStorage persistence
    const STORAGE_PREFIX = `spa_state_${path.includes('/PICTO/') ? 'picto' : path.includes('/LUPTO/') ? 'lupto' : 'municipal'}_`;

    // Save UI state of active tab
    function saveTabUIState(pageName) {
        const tabEl = loadedTabs[pageName];
        if (!tabEl) return;

        const state = {
            scroll: {
                windowX: window.scrollX,
                windowY: window.scrollY,
                bodyTop: document.querySelector('.page-body')?.scrollTop || 0,
                mainTop: document.querySelector('.main-content')?.scrollTop || 0
            },
            inputs: {}
        };

        // Find all inputs (search, text, checkbox, radio) and select elements
        tabEl.querySelectorAll('input, select').forEach(el => {
            if (!el.id) return; // Only save states for elements with IDs
            if (el.type === 'file') return; // Skip file inputs (can't set programmatically)
            if (el.type === 'checkbox' || el.type === 'radio') {
                state.inputs[el.id] = el.checked;
            } else {
                state.inputs[el.id] = el.value;
            }
        });

        // Find Leaflet map state if present
        const mapEl = tabEl.querySelector('.leaflet-container, [id*="map"]');
        if (mapEl && mapEl._leaflet_map) {
            const mapInstance = mapEl._leaflet_map;
            try {
                state.map = {
                    center: [mapInstance.getCenter().lat, mapInstance.getCenter().lng],
                    zoom: mapInstance.getZoom()
                };
            } catch (e) { }
        }

        sessionStorage.setItem(STORAGE_PREFIX + pageName, JSON.stringify(state));
    }

    // Restore UI state of active tab
    function restoreTabUIState(pageName) {
        const tabEl = loadedTabs[pageName];
        if (!tabEl) return;

        const raw = sessionStorage.getItem(STORAGE_PREFIX + pageName);
        if (!raw) return;

        try {
            const state = JSON.parse(raw);

            // Restore inputs & selects
            if (state.inputs) {
                Object.keys(state.inputs).forEach(id => {
                    const el = tabEl.querySelector('#' + id);
                    if (!el) return;
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        el.checked = state.inputs[id];
                    } else {
                        el.value = state.inputs[id];
                    }
                    // Trigger events so that view scripts update UI dynamically
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            // Restore map state
            if (state.map && window.L) {
                let attempts = 0;
                const mapInterval = setInterval(() => {
                    const mapEl = tabEl.querySelector('.leaflet-container, [id*="map"]');
                    if (mapEl && mapEl._leaflet_map) {
                        try {
                            mapEl._leaflet_map.setView(state.map.center, state.map.zoom);
                            mapEl._leaflet_map.invalidateSize();
                        } catch (e) { }
                        clearInterval(mapInterval);
                    }
                    attempts++;
                    if (attempts > 30) clearInterval(mapInterval);
                }, 500);
            }

            // Restore scroll position
            if (state.scroll) {
                tabScrollStates[pageName] = state.scroll;
                if (pageName === activeTabName) {
                    window.scrollTo(state.scroll.windowX, state.scroll.windowY);
                    const body = document.querySelector('.page-body');
                    if (body) body.scrollTop = state.scroll.bodyTop;
                    const main = document.querySelector('.main-content');
                    if (main) main.scrollTop = state.scroll.mainTop;
                }
            }
        } catch (e) {
            console.error('Failed to restore UI state for ' + pageName, e);
        }
    }

    // Debounced automatic save on input/change events
    let saveTimeout;
    function debouncedSaveState() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveTabUIState(activeTabName);
        }, 1000);
    }

    // Override Leaflet's L.map to capture map instances for size invalidation
    function patchLeaflet() {
        if (window.L && L.map && !L.map.isPatched) {
            const originalLMap = L.map;
            L.map = function (id, options) {
                const mapInstance = originalLMap.call(L, id, options);
                const el = typeof id === 'string' ? document.getElementById(id) : id;
                if (el) {
                    el._leaflet_map = mapInstance;
                }
                return mapInstance;
            };
            L.map.isPatched = true;
        }
    }

    // Call once at startup. If Leaflet isn't loaded yet it will be patched
    // after the first tab that uses it finishes loading (see loadTab).
    patchLeaflet();

    // Helper: Execute script tags sequentially
    function executeScripts(container) {
        // Pre-mark all existing script tags in the page as loaded
        document.querySelectorAll('script[src]').forEach(s => {
            if (!s.hasAttribute('data-loaded')) {
                s.setAttribute('data-loaded', 'true');
            }
        });

        const scripts = Array.from(container.querySelectorAll('script'));
        let index = 0;

        function runNext() {
            if (index >= scripts.length) return Promise.resolve();
            const oldScript = scripts[index];
            index++;

            return new Promise((resolve) => {
                const newScript = document.createElement('script');

                // Copy all attributes (except defer/async to guarantee synchronous execution order)
                Array.from(oldScript.attributes).forEach(attr => {
                    if (attr.name !== 'defer' && attr.name !== 'async') {
                        newScript.setAttribute(attr.name, attr.value);
                    }
                });

                // Set async to false so scripts execute in exact insertion order
                newScript.async = false;

                // Library load check: don't reload Leaflet or Chart.js if they are already on window
                const src = oldScript.src || '';
                const isLibrary = src.includes('unpkg.com') || src.includes('cdnjs.cloudflare.com') || src.includes('jsdelivr.net') || src.includes('cdn.jsdelivr.net');

                if (isLibrary) {
                    if (src.includes('leaflet') && window.L) {
                        resolve(runNext());
                        return;
                    }
                    if (src.includes('chart') && window.Chart) {
                        resolve(runNext());
                        return;
                    }
                    
                    const existing = document.querySelector(`script[src="${src}"]`);
                    if (existing) {
                        if (existing.getAttribute('data-loaded') === 'true') {
                            resolve(runNext());
                        } else {
                            existing.addEventListener('load', () => resolve(runNext()));
                            existing.addEventListener('error', () => resolve(runNext()));
                        }
                        return;
                    }
                }

                // Check script type/source
                if (oldScript.src) {
                    newScript.onload = () => {
                        newScript.setAttribute('data-loaded', 'true');
                        resolve(runNext());
                    };
                    newScript.onerror = () => {
                        newScript.setAttribute('data-loaded', 'error');
                        resolve(runNext());
                    };
                    document.body.appendChild(newScript);
                } else {
                    newScript.textContent = oldScript.textContent;
                    document.body.appendChild(newScript);
                    resolve(runNext());
                }
            });
        }

        return runNext();
    }

    // Initialize SPA container
    function initSpa() {
        const pageBody = document.querySelector('.page-body');
        if (!pageBody) return;

        // Create the SPA tab container
        const spaContainer = document.createElement('div');
        spaContainer.id = 'spa-tab-container';
        spaContainer.style.width = '100%';
        spaContainer.style.height = '100%';

        // Wrap current pageBody contents inside the initial tab
        const initialTab = document.createElement('div');
        initialTab.id = `spa-tab-${initialPage}`;
        initialTab.className = 'spa-tab-content active-tab';

        while (pageBody.firstChild) {
            initialTab.appendChild(pageBody.firstChild);
        }

        spaContainer.appendChild(initialTab);
        pageBody.appendChild(spaContainer);

        loadedTabs[initialPage] = initialTab;

        // Restore initial page's saved state if any
        restoreTabUIState(initialPage);

        // Intercept relative .php links (sidebar, dropdowns, buttons, inline links)
        document.addEventListener('click', function (e) {
            const navLink = e.target.closest('a');
            if (!navLink) return;

            const href = navLink.getAttribute('href');
            // Skip invalid, external, or logout links
            if (!href || href.startsWith('#') || href.includes('logout.php') || href.startsWith('http') || href.startsWith('//') || href.includes(':')) {
                return;
            }

            if (window.MUST_CHANGE_PASSWORD) {
                e.preventDefault();
                return;
            }

            // Intercept local relative .php navigation links
            if (href.endsWith('.php') || href.includes('.php?')) {
                e.preventDefault();
                const pageName = href.split('?')[0];
                switchTab(pageName);
            }
        });

        // Track changes to save state dynamically
        spaContainer.addEventListener('input', debouncedSaveState);
        spaContainer.addEventListener('change', debouncedSaveState);

        // Add CSS styles for tabs
        const style = document.createElement('style');
        style.textContent = `
            .spa-tab-content {
                display: none;
                width: 100%;
                height: 100%;
            }
            .spa-tab-content.active-tab {
                display: block;
            }
        `;
        document.head.appendChild(style);

        // Bind global refresh button
        const refreshBtn = document.getElementById('spaRefreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function (e) {
                e.preventDefault();
                refreshActiveTab();
            });
        }

        // Restore active tab from sessionStorage if we re-loaded the base dashboard
        if (initialPage === 'dashboard.php') {
            const savedActiveTab = sessionStorage.getItem(STORAGE_PREFIX + 'active_tab');
            if (savedActiveTab && savedActiveTab !== 'dashboard.php') {
                switchTab(savedActiveTab);
            }
        }
    }

    // Switch tab logic
    function switchTab(pageName, pushToHistory = true) {
        if (pageName === activeTabName) return;

        const currentTab = loadedTabs[activeTabName];
        if (currentTab) {
            // Save state & scroll position before switching away
            saveTabUIState(activeTabName);

            // Stop auto-refresh for dashboard if switching away
            if (activeTabName === 'dashboard.php' && typeof window.stopAutoRefresh === 'function') {
                window.stopAutoRefresh();
            }

            currentTab.classList.remove('active-tab');
            currentTab.style.display = 'none';
        }

        activeTabName = pageName;
        sessionStorage.setItem(STORAGE_PREFIX + 'active_tab', pageName);

        // Update active class in sidebar links
        document.querySelectorAll('.sidebar-nav .nav-item').forEach(link => {
            const href = link.getAttribute('href');
            if (href === pageName) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // Update active class in header dropdowns
        document.querySelectorAll('.dd-item').forEach(link => {
            const href = link.getAttribute('href');
            if (href === pageName) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        if (pushToHistory) {
            history.pushState({ page: pageName }, '', pageName);
        }

        // If tab is already loaded, show it
        if (loadedTabs[pageName]) {
            const targetTab = loadedTabs[pageName];
            targetTab.style.display = 'block';
            targetTab.classList.add('active-tab');

            // Restore scroll & state
            restoreTabUIState(pageName);

            // Start auto-refresh for dashboard if switching to it
            if (pageName === 'dashboard.php' && typeof window.startAutoRefresh === 'function') {
                window.startAutoRefresh();
            }

            // Invalidate Leaflet map sizes in the shown tab
            const maps = targetTab.querySelectorAll('.leaflet-container, [id*="map"]');
            maps.forEach(mEl => {
                if (mEl._leaflet_map) {
                    mEl._leaflet_map.invalidateSize();
                }
            });

            // Dispatch tabshow event
            targetTab.dispatchEvent(new CustomEvent('tabshow', { bubbles: true }));
            window.dispatchEvent(new Event('resize'));
            return;
        }

        // Lazy load the tab
        loadTab(pageName);
    }

    // Load tab from server (with sessionStorage cache fallback)
    function loadTab(pageName) {
        const icon = document.getElementById('spaRefreshIcon');
        if (icon) icon.classList.add('fa-spin');

        // Check for cached HTML in sessionStorage (skip if we want fresh data)
        const cacheKey = STORAGE_PREFIX + 'html_' + pageName;
        const cachedHtml = sessionStorage.getItem(cacheKey);

        if (cachedHtml) {
            injectTabHtml(pageName, cachedHtml, icon);
            return;
        }

        fetch(pageName, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPA-Request': 'true'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.text();
            })
            .then(html => {
                try {
                    sessionStorage.setItem(cacheKey, html);
                } catch (e) { }
                injectTabHtml(pageName, html, icon);
            })
            .catch(error => {
                console.error('Failed to load tab:', error);
                if (icon) icon.classList.remove('fa-spin');
            });
    }

    function injectTabHtml(pageName, html, icon) {
        let tabWrapper = loadedTabs[pageName];
        if (!tabWrapper) {
            tabWrapper = document.createElement('div');
            tabWrapper.id = `spa-tab-${pageName}`;
            tabWrapper.className = 'spa-tab-content';
            const spaContainer = document.getElementById('spa-tab-container');
            if (spaContainer) {
                spaContainer.appendChild(tabWrapper);
            }
            loadedTabs[pageName] = tabWrapper;
        }

        tabWrapper.innerHTML = html;

        tabWrapper.style.display = 'block';
        tabWrapper.classList.add('active-tab');

        const activeTab = loadedTabs[activeTabName];
        if (activeTab && activeTab !== tabWrapper) {
            activeTab.classList.remove('active-tab');
            activeTab.style.display = 'none';
        }

        const queuedListeners = [];
        const originalAddEventListener = document.addEventListener;
        document.addEventListener = function (type, listener, options) {
            if (type === 'DOMContentLoaded') {
                queuedListeners.push(listener);
            } else {
                originalAddEventListener.call(document, type, listener, options);
            }
        };

        executeScripts(tabWrapper).then(() => {
            document.addEventListener = originalAddEventListener;

            queuedListeners.forEach(listener => {
                try {
                    listener();
                } catch (e) {
                    console.error('Error in deferred DOMContentLoaded listener:', e);
                }
            });

            restoreTabUIState(pageName);

            if (pageName === 'dashboard.php' && typeof window.startAutoRefresh === 'function') {
                window.startAutoRefresh();
            }

            patchLeaflet();

            const maps = tabWrapper.querySelectorAll('.leaflet-container, [id*="map"]');
            maps.forEach(mEl => {
                if (mEl._leaflet_map) {
                    mEl._leaflet_map.invalidateSize();
                }
            });

            tabWrapper.dispatchEvent(new CustomEvent('tabshow', { bubbles: true }));
            window.dispatchEvent(new Event('resize'));

            if (icon) icon.classList.remove('fa-spin');
        });
    }

    // Refresh active tab
    function refreshActiveTab() {
        const icon = document.getElementById('spaRefreshIcon');
        if (icon) icon.classList.add('fa-spin');

        // Clear client-side map cache on manual or programmatic refresh
        if (window.MAP_CACHE && typeof window.MAP_CACHE.clear === 'function') {
            window.MAP_CACHE.clear();
        }

        // Soft refresh: for dashboards, only re-fetch API data without reloading the page
        if (activeTabName === 'dashboard.php' && typeof window.softRefreshDashboard === 'function') {
            window.softRefreshDashboard().finally(() => {
                if (icon) icon.classList.remove('fa-spin');
            });
            return;
        }

        // Soft refresh: for tourist spots, re-fetch spots + municipalities and re-render
        if (activeTabName === 'tourist-spots.php' && typeof window.softRefreshTouristSpots === 'function') {
            window.softRefreshTouristSpots().finally(() => {
                if (icon) icon.classList.remove('fa-spin');
            });
            return;
        }

        // Soft refresh: for fare data, re-fetch guides and stats
        if (activeTabName === 'fare-data.php' && typeof window.softRefreshFareData === 'function') {
            window.softRefreshFareData().finally(() => {
                if (icon) icon.classList.remove('fa-spin');
            });
            return;
        }

        // User Management custom reload
        if (activeTabName === 'user-management.php' && typeof window.refreshTable === 'function') {
            window.refreshTable().then(() => {
                if (icon) icon.classList.remove('fa-spin');
            }).catch(() => {
                if (icon) icon.classList.remove('fa-spin');
            });
            return;
        }

        // Clear cached HTML for this tab so we get a fresh copy
        try {
            sessionStorage.removeItem(STORAGE_PREFIX + 'html_' + activeTabName);
        } catch (e) { }

        // General reload: fetch page content again and re-evaluate
        loadTab(activeTabName);
        setTimeout(() => {
            if (icon) icon.classList.remove('fa-spin');
        }, 1000);
    }

    // Listen to visibilitychange to save state and pause auto-refreshes
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            if (activeTabName === 'dashboard.php' && typeof window.stopAutoRefresh === 'function') {
                window.stopAutoRefresh();
            }
            saveTabUIState(activeTabName);
            sessionStorage.setItem(STORAGE_PREFIX + 'active_tab', activeTabName);
        } else {
            if (activeTabName === 'dashboard.php' && typeof window.startAutoRefresh === 'function') {
                window.startAutoRefresh();
            }
            restoreTabUIState(activeTabName);
        }
    });

    // Save state on unload
    window.addEventListener('beforeunload', () => {
        saveTabUIState(activeTabName);
        sessionStorage.setItem(STORAGE_PREFIX + 'active_tab', activeTabName);
    });

    // Listen to popstate for back/forward browser buttons
    window.addEventListener('popstate', function (e) {
        const pageName = e.state?.page || getPageName(window.location.href);
        switchTab(pageName, false);
    });

    // ── Smooth number animation helper ──────────────────────────────────────────
    window.animateKpiValue = function (element, newValue) {
        if (!element) return;
        const cleanNumStr = element.textContent.trim().replace(/,/g, '');
        let prev = parseInt(cleanNumStr, 10);
        if (isNaN(prev)) prev = 0;

        let target = parseInt(newValue, 10);
        if (isNaN(target)) {
            element.textContent = newValue;
            return;
        }

        if (prev === target) {
            element.textContent = target.toLocaleString();
            return;
        }

        const duration = 1000;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeProgress = progress * (2 - progress);
            const currentVal = Math.round(prev + (target - prev) * easeProgress);
            element.textContent = currentVal.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = target.toLocaleString();
            }
        }
        requestAnimationFrame(update);
    };

    // ── Global Real-Time Refresh Notifier ───────────────────────────────────────
    window.notifyTouristSpotChanged = function () {
        void 0;

        const path = window.location.pathname;
        const currentRole = path.includes('/PICTO/') ? 'picto' : path.includes('/LUPTO/') ? 'lupto' : 'municipal';
        const STORAGE_PREFIX = `spa_state_${currentRole}_`;
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith(STORAGE_PREFIX + 'html_')) {
                sessionStorage.removeItem(key);
            }
        });

        const baseUrl = window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
        const spotsUrl = currentRole === 'municipal' ? `${baseUrl}/api/municipal/tourist-spots` : `${baseUrl}/api/tourist-spots`;

        if (window.API_CONFIG && typeof window.API_CONFIG.get === 'function') {
            return Promise.all([
                window.API_CONFIG.get(spotsUrl),
                window.API_CONFIG.get(`${baseUrl}/api/municipalities`)
            ]).then(([spotsRes, muniRes]) => {
                const freshSpots = (spotsRes && spotsRes.data) || spotsRes || [];
                const freshMunis = (muniRes && muniRes.municipalities) || (muniRes && muniRes.data) || muniRes || [];

                window.touristSpotsData = freshSpots;
                window.municipalitiesData = freshMunis;
                window.touristSpotsAll = freshSpots;
                window.municipalitiesAll = freshMunis;

                // Sync to window cache objects so that they are instantly available on page switches
                const luptoCache = window['__LUPTO_TOURIST_SPOTS_CACHE__'];
                if (luptoCache) {
                    luptoCache.spots = freshSpots;
                    luptoCache.munis = freshMunis;
                    luptoCache.timestamp = Date.now();
                }
                const muniCache = window['__MUNI_TOURIST_SPOTS_CACHE__'];
                if (muniCache) {
                    muniCache.spots = freshSpots;
                    muniCache.munis = freshMunis;
                    muniCache.timestamp = Date.now();
                }

                if (typeof window.softRefreshDashboard === 'function') {
                    window.softRefreshDashboard();
                }

                if (typeof window.softRefreshTouristSpots === 'function') {
                    window.softRefreshTouristSpots(freshSpots, freshMunis);
                }

                if (typeof window.refreshLuptoMap === 'function') {
                    window.refreshLuptoMap();
                }

                if (typeof window.refreshMunicipalMap === 'function') {
                    window.refreshMunicipalMap();
                }

                if (typeof window.refreshAnalytics === 'function') {
                    window.refreshAnalytics(true);
                }

                if (typeof window.refreshLeaderboard === 'function') {
                    window.refreshLeaderboard();
                }
            }).catch(err => {
                if (err.name !== 'AbortError') {
                    console.error("❌ Failed to fetch updated data for real-time refresh:", err);
                }
            });
        }
        return Promise.resolve();
    };

    window.refreshActiveTab = refreshActiveTab;

    window.notifyFareDataChanged = function() {
        void 0;

        const path = window.location.pathname;
        const currentRole = path.includes('/PICTO/') ? 'picto' : path.includes('/LUPTO/') ? 'lupto' : 'municipal';
        const STORAGE_PREFIX = `spa_state_${currentRole}_`;
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith(STORAGE_PREFIX + 'html_')) {
                sessionStorage.removeItem(key);
            }
        });

        if (typeof window.softRefreshDashboard === 'function') {
            window.softRefreshDashboard();
        }

        if (typeof window.softRefreshFareData === 'function') {
            window.softRefreshFareData();
        }

        if (typeof window.am_refresh === 'function') {
            window.am_refresh();
        }
    };

    window.notifyUserChanged = function() {
        void 0;

        const path = window.location.pathname;
        const currentRole = path.includes('/PICTO/') ? 'picto' : path.includes('/LUPTO/') ? 'lupto' : 'municipal';
        const STORAGE_PREFIX = `spa_state_${currentRole}_`;
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith(STORAGE_PREFIX + 'html_')) {
                sessionStorage.removeItem(key);
            }
        });

        if (typeof window.softRefreshDashboard === 'function') {
            window.softRefreshDashboard();
        }

        if (typeof window.refreshTable === 'function') {
            window.refreshTable();
        }
    };

    // Run initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSpa);
    } else {
        initSpa();
    }

})();

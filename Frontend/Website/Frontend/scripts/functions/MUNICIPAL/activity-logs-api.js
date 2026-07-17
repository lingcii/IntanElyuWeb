/**
 * MUNICIPAL Activity Logs API Functions
 * Uses centralized API_CONFIG for real-time database operations
 */

const MUNICIPAL_API = window.API_CONFIG?.MUNICIPAL || 'http://localhost:8000/api/municipal';
let refreshTimer = null;

// ── Fetch Activity Logs from Database ───────────────────────────────────────
export async function fetchActivityLogs() {
    try {
        const data = await window.API_CONFIG.get(`${MUNICIPAL_API}/activity-logs`);
        return data;
    } catch (err) {
        console.error('[Activity Logs] fetchActivityLogs error:', err);
        throw err;
    }
}

// ── Real-time Auto Refresh ────────────────────────────────────────────────────
export function startAutoRefresh(callback) {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(async () => {
        try {
            await callback();
        } catch (err) {
            void 0;
        }
    }, 30000);
}

export function stopAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
}

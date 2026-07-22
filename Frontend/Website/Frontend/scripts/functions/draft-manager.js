/**
 * draft-manager.js
 * Unified Draft Saving Functionality for Add Tourist Spot (LUPTO & Municipal)
 */

(function () {
    'use strict';

    let _activeDraftId = null;
    let _isFormDirty = false;
    let _autoSaveTimer = null;
    let _pendingDraft = null;

    function getBaseUrl() {
        return window.API_CONFIG?.BASE_URL || (`http://${window.location.hostname || '127.0.0.1'}:8000`);
    }

    // ── Inject Draft Modals into DOM ──────────────────────────────────────────
    function ensureDraftModalsExist() {
        if (document.getElementById('draftFoundModal')) return;

        const container = document.createElement('div');
        container.id = 'draftModalsContainer';
        container.innerHTML = `
<!-- Draft Found Dialog Modal -->
<div class="modal" id="draftFoundModal" style="z-index: 10500;">
    <div class="modal-content" style="max-width: 460px; padding: 24px; text-align: center; border-radius: 16px; background: #fff; box-shadow: 0 20px 60px rgba(0,0,0,0.25); margin: auto;">
        <div style="width: 56px; height: 56px; background: #EEF2FF; color: #4F46E5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px;">
            <i class="fas fa-file-signature"></i>
        </div>
        <h3 style="margin: 0 0 8px; font-size: 18px; color: #1E293B; font-weight: 700;">Draft Found</h3>
        <p style="margin: 0 0 20px; font-size: 14px; color: #64748B; line-height: 1.5;" id="draftFoundMessage">
            You have an unfinished tourist spot saved as a draft. Would you like to continue editing it?
        </p>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button type="button" class="btn btn-primary" id="btnContinueDraft" style="width: 100%; padding: 11px; font-weight: 600; border-radius: 8px;">
                <i class="fas fa-pen-to-square"></i> Continue Editing
            </button>
            <button type="button" class="btn btn-secondary" id="btnStartNewDraft" style="width: 100%; padding: 11px; font-weight: 600; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; border-radius: 8px;">
                <i class="fas fa-plus"></i> Start New Tourist Spot
            </button>
            <button type="button" class="btn btn-danger" id="btnDeleteDraft" style="width: 100%; padding: 11px; font-weight: 600; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 8px;">
                <i class="fas fa-trash-can"></i> Delete Draft
            </button>
        </div>
    </div>
</div>

<!-- Save as Draft Confirmation Dialog Modal -->
<div class="modal" id="saveAsDraftConfirmModal" style="z-index: 10500;">
    <div class="modal-content" style="max-width: 460px; padding: 24px; text-align: center; border-radius: 16px; background: #fff; box-shadow: 0 20px 60px rgba(0,0,0,0.25); margin: auto;">
        <div style="width: 56px; height: 56px; background: #FEF3C7; color: #D97706; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px;">
            <i class="fas fa-bookmark"></i>
        </div>
        <h3 style="margin: 0 0 8px; font-size: 18px; color: #1E293B; font-weight: 700;">Save as Draft?</h3>
        <p style="margin: 0 0 20px; font-size: 14px; color: #64748B; line-height: 1.5;">
            You have unsaved information for this tourist spot. Would you like to save it as a draft so you can continue later?
        </p>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button type="button" class="btn btn-primary" id="btnConfirmSaveDraft" style="width: 100%; padding: 11px; font-weight: 600; background: #059669; border-color: #059669; border-radius: 8px; color: #fff;">
                <i class="fas fa-floppy-disk"></i> Save as Draft
            </button>
            <button type="button" class="btn btn-danger" id="btnConfirmDiscardDraft" style="width: 100%; padding: 11px; font-weight: 600; background: #F3F4F6; color: #4B5563; border: 1px solid #D1D5DB; border-radius: 8px;">
                <i class="fas fa-trash"></i> Discard
            </button>
            <button type="button" class="btn btn-secondary" id="btnConfirmContinueEditing" style="width: 100%; padding: 11px; font-weight: 600; background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; border-radius: 8px;">
                <i class="fas fa-arrow-left"></i> Continue Editing
            </button>
        </div>
    </div>
</div>
        `;
        document.body.appendChild(container);
    }

    // ── API Actions ───────────────────────────────────────────────────────────
    async function fetchActiveDraft() {
        try {
            const base = getBaseUrl();
            const res = await window.API_CONFIG.get(`${base}/api/tourist-spots/draft`);
            return res?.draft || null;
        } catch (_) {
            return null;
        }
    }

    async function saveDraftPayload(payload) {
        try {
            const base = getBaseUrl();
            const res = await window.API_CONFIG.post(`${base}/api/tourist-spots/draft`, payload);
            if (res?.draft?.id) {
                _activeDraftId = res.draft.id;
            }
            return res;
        } catch (e) {
            console.error('Failed to save draft:', e);
            return null;
        }
    }

    async function deleteDraftApi(draftId) {
        if (!draftId) return;
        try {
            const base = getBaseUrl();
            await window.API_CONFIG.delete(`${base}/api/tourist-spots/draft/${draftId}`);
            _activeDraftId = null;
        } catch (e) {
            console.error('Failed to delete draft:', e);
        }
    }

    // Export globally
    window.DraftManager = {
        ensureModals: ensureDraftModalsExist,
        fetchDraft: fetchActiveDraft,
        saveDraft: saveDraftPayload,
        deleteDraft: deleteDraftApi,

        getActiveDraftId: () => _activeDraftId,
        setActiveDraftId: (id) => { _activeDraftId = id; },
        
        isDirty: () => _isFormDirty,
        setDirty: (dirty = true) => { _isFormDirty = dirty; },

        getPendingDraft: () => _pendingDraft,
        setPendingDraft: (d) => { _pendingDraft = d; },

        stopAutoSave: () => {
            if (_autoSaveTimer) {
                clearInterval(_autoSaveTimer);
                _autoSaveTimer = null;
            }
        },
        startAutoSave: (callback) => {
            window.DraftManager.stopAutoSave();
            _autoSaveTimer = setInterval(() => {
                if (_isFormDirty && typeof callback === 'function') {
                    callback();
                }
            }, 45000); // Auto-save every 45 seconds
        }
    };
})();

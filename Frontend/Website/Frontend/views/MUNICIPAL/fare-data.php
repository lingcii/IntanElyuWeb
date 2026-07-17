<?php
require_once __DIR__ . '/../../session-bridge.php';
if ($_SESSION['user_role'] !== 'municipal' && !str_ends_with($_SESSION['user_role'], '_mto')) {
    header('Location: ../../login.php');
    exit;
}
$pageTitle = 'Municipal Fare Data';
ob_start();
?>
<link rel="stylesheet" href="../../css/MUNICIPAL/fare-data.css">

<div class="fd-container">

    <div class="fd-info-banner">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Tricycle Fare Management</strong>
            <p>Municipal Tourism Offices can upload and manage <strong>Tricycle</strong> fare matrices only. All other vehicle types (PUB, PUJ, Van) are managed by PICTO.</p>
        </div>
    </div>

    <?php
    $userModel = \App\Models\User::with('municipality')->find($_SESSION['user_id']);
    $muniName = $userModel && $userModel->municipality ? $userModel->municipality->name : 'Your Municipality';
    ?>
    <div class="fd-section-card">
        <div class="fd-section-header">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload Fare Guide CSV</h3>
        </div>
        <div class="fd-section-body-pad">
            <!-- Metadata Form for Upload -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                <div class="fd-form-group">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Title (Optional)</label>
                    <input type="text" id="upTitle" class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;" placeholder="e.g., Tricycle Fare Matrix 2026">
                </div>
                <div class="fd-form-group">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Vehicle Type</label>
                    <input type="text" value="Tricycle" disabled class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;background:#f1f5f9;">
                </div>
                <div class="fd-form-group">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Municipality</label>
                    <input type="text" value="<?php echo htmlspecialchars($muniName); ?>" disabled class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;background:#f1f5f9;">
                </div>
                <div class="fd-form-group">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Effective Date (Optional)</label>
                    <input type="date" id="upEffectiveDate" class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;">
                </div>
            </div>

            <div class="fd-upload-zone" id="uploadArea">
                <i class="fas fa-file-csv fd-upload-icon"></i>
                <p class="fd-upload-title">Drag and drop your CSV fare guide here, or click to browse</p>
                <p class="fd-upload-hint">Upload Tricycle fare matrix CSV (Max 20MB)</p>
                <input type="file" id="fareFileInput" accept=".csv,text/csv" style="display:none;">
                <button class="fd-btn-browse" onclick="document.getElementById('fareFileInput').click();">
                    <i class="fas fa-folder-open"></i> Browse Files
                </button>
            </div>
            <div class="fd-progress-wrap" id="uploadProgress">
                <div class="fd-progress-bar">
                    <div class="fd-progress-fill" id="progressFill"></div>
                </div>
                <p class="fd-progress-text" id="progressText"></p>
            </div>
            <div class="fd-upload-result" id="uploadResult"></div>
        </div>
    </div>

    <div class="fd-section-card">
        <div class="fd-section-header">
            <h3><i class="fas fa-book"></i> Fare Guides</h3>
            <div style="display:flex;gap:8px;">
                <button class="fd-btn-refresh" onclick="fd_openAddModal()" style="background:#10b981;color:white;border-color:#10b981;">
                    <i class="fas fa-plus"></i> Add Fare Matrix
                </button>
                <button class="fd-btn-refresh" onclick="loadFareGuides()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        <div class="fd-section-body">
            <table class="fd-data-table" id="fareGuidesTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Vehicle Type</th>
                        <th>Region</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="fareGuidesBody">
                    <tr class="fd-loading-row"><td colspan="7">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="fd-section-card fd-matrix-panel" id="fareMatrixSection">
        <div class="fd-section-header">
            <h3><i class="fas fa-table"></i> Fare Matrix</h3>
            <button class="fd-btn-refresh" onclick="document.getElementById('fareMatrixSection').classList.remove('active');">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        <div class="fd-section-body fd-matrix-body-wrap">
            <table class="fd-data-table" id="fareMatrixTable">
                <thead>
                    <tr>
                        <th>Distance (km)</th>
                        <th>Regular Fare</th>
                        <th>Student / Senior / PWD</th>
                        <th>Savings</th>
                    </tr>
                </thead>
                <tbody id="fareMatrixBody"></tbody>
            </table>
        </div>
    </div>

    <div class="fd-section-card">
        <div class="fd-section-header">
            <h3><i class="fas fa-history"></i> Upload History</h3>
        </div>
        <div class="fd-section-body">
            <table class="fd-data-table" id="uploadHistoryTable">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Records</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="uploadHistoryBody">
                    <tr class="fd-loading-row"><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="fd-modal-overlay" id="detailsModal">
        <div class="fd-modal">
            <div class="fd-modal-header">
                <h3 id="modalTitle">Details</h3>
                <button class="fd-modal-close" onclick="document.getElementById('detailsModal').classList.remove('active');">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="fd-modal-body" id="modalBody"></div>
        </div>
    </div>

    <!-- ── Add/Edit manual modal ─────────────────────────────── -->
    <div class="fd-modal-overlay" id="fdAddEditModal" onclick="if(event.target===this)fd_closeAddEdit()">
        <div class="fd-modal" style="max-width:600px;">
            <div class="fd-modal-header">
                <h3 id="fdAddEditTitle"><i class="fas fa-plus"></i> Add Fare Matrix</h3>
                <button class="fd-modal-close" onclick="fd_closeAddEdit()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form id="fdAddEditForm" onsubmit="fd_saveManual(event)">
                <input type="hidden" id="aeGuideId">
                <div class="fd-modal-body" style="padding:20px;">
                    <div class="fd-form-group" style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Title</label>
                        <input type="text" id="aeTitle" required class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;" placeholder="e.g., Tricycle Fare Matrix 2026">
                    </div>
                    <div style="display:flex;gap:12px;margin-bottom:12px;">
                        <div class="fd-form-group" style="flex:1;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Vehicle Type</label>
                            <input type="text" id="aeVehicleType" value="Tricycle" readonly disabled class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;background:#f1f5f9;">
                        </div>
                        <div class="fd-form-group" style="flex:1;">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Municipality</label>
                            <input type="text" id="aeRegion" value="<?php echo htmlspecialchars($muniName); ?>" readonly disabled class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;background:#f1f5f9;">
                        </div>
                    </div>
                    <div class="fd-form-group" style="margin-bottom:16px;">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--text-primary);">Effective Date</label>
                        <input type="date" id="aeEffectiveDate" required class="fd-input" style="width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:4px;">
                    </div>

                    <div style="border-top:1px solid #bfdbfe;padding-top:12px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <h4 style="margin:0;font-size:13px;color:var(--text-primary);"><i class="fas fa-list"></i> Fare Matrix Rows</h4>
                            <button type="button" class="fd-btn-refresh" style="padding:4px 8px;font-size:11px;background:#10b981;color:white;border-color:#10b981;" onclick="ae_addRow()">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                        </div>
                        <div style="max-height:200px;overflow-y:auto;border:1px solid #bfdbfe;border-radius:4px;">
                            <table style="width:100%;border-collapse:collapse;font-size:12px;" id="aeRowsTable">
                                <thead style="background:#f8fafc;position:sticky;top:0;z-index:1;">
                                    <tr>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid #bfdbfe;">Distance (km)</th>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid #bfdbfe;">Regular Fare (₱)</th>
                                        <th style="padding:6px;text-align:left;border-bottom:1px solid #bfdbfe;">Discounted (₱)</th>
                                        <th style="padding:6px;width:40px;border-bottom:1px solid #bfdbfe;"></th>
                                    </tr>
                                </thead>
                                <tbody id="aeRowsBody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="fd-modal-footer" style="display:flex;gap:8px;justify-content:flex-end;padding:12px;border-top:1px solid #bfdbfe;">
                    <button type="button" class="fd-btn-refresh" style="margin:0;" onclick="fd_closeAddEdit()">Cancel</button>
                    <button type="submit" class="fd-btn-browse" style="margin:0;background:#2563eb;color:white;border-color:#2563eb;" id="aeSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fd-toast-container" id="fdToastContainer"></div>

</div>

<script src="../../scripts/functions/MUNICIPAL/fare-data-api.js?v=<?php echo filemtime(__DIR__ . '/../../scripts/functions/MUNICIPAL/fare-data-api.js'); ?>"></script>
<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) echo $extraHeadContent;
    echo $pageContent;
    exit;
}
include '../../components/sections.php';

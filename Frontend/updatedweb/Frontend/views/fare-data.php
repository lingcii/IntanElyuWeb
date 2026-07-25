<?php
// Transportation Fare Matrix view — LUPTO, PICTO, and Municipal roles.
// Handles fare guide listings, CSV fare matrix previewing/exporting, and role-based fare guide creation.

require_once __DIR__ . '/../session-bridge.php';
$allowedRoles = ['lupto', 'picto', 'municipal'];
require_once __DIR__ . '/_role_guard.php';
$pageTitle = strtoupper($userRole) . ' Fare Matrix';
ob_start();
?>
<link rel="stylesheet" href="../css/fare-data.css">

<div class="fd-container">

    <!-- Access & Permissions Info Banner -->
    <div class="fd-info-banner">
        <i class="fas fa-info-circle"></i>
        <div>
            <?php if ($userRole === 'lupto'): ?>
                <strong>View-Only Access</strong>
                <p>LUPTO can search, view, and download fare information. Upload and edit actions are managed by PITCO and Municipal Tourism Offices.</p>
            <?php elseif ($userRole === 'picto'): ?>
                <strong>PICTO Management Access</strong>
                <p>You can create, edit, upload, and manage transportation fare guides for all vehicle types across La Union.</p>
            <?php else: ?>
                <strong>Municipal Management Access</strong>
                <p>You can create and manage transportation fare guides for <strong>Tricycle</strong> in your municipality.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Bar, Vehicle Filter, Add Fare Guide & Refresh Actions -->
    <div class="fd-search-bar">
        <div class="fd-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="fd-search-input" placeholder="Search by title, region, or creator..." oninput="filterFareGuides()">
        </div>
        <select id="vehicleFilter" class="fd-filter-select" onchange="filterFareGuides()">
            <option value="">All Vehicle Types</option>
            <option value="PUB_Aircon">PUB Aircon</option>
            <option value="PUB_Ordinary">PUB Ordinary</option>
            <option value="PUJ_Aircon">PUJ Aircon</option>
            <option value="PUJ_Ordinary">PUJ Ordinary</option>
            <option value="Tricycle">Tricycle</option>
            <option value="Van">Van</option>
        </select>

        <?php if ($userRole !== 'lupto'): ?>
        <button class="fd-btn-refresh" style="background:#073B6B;color:#fff;border-color:#073B6B;" onclick="openAddFareGuideModal()">
            <i class="fas fa-plus"></i> Add Fare Guide
        </button>
        <?php endif; ?>

        <button class="fd-btn-refresh" onclick="loadFareGuides()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <span class="fd-guide-count" id="fareGuidesCount"></span>
    </div>

    <!-- Transportation Fare Guides Cards Grid Container -->
    <div class="fd-cards-grid" id="fareGuidesGrid">
        <div class="fd-loading-spinner" style="grid-column: 1 / -1;">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Loading fare guides...</p>
        </div>
    </div>

    <!-- Detailed Fare Matrix Table Panel (Expandable on card click) -->
    <div class="fd-matrix-panel" id="fareMatrixSection">
        <div class="fd-matrix-header">
            <h3 id="fareMatrixTitle"><i class="fas fa-table"></i> Fare Matrix</h3>
            <div class="fd-matrix-actions">
                <button class="fd-btn-export" onclick="exportFareMatrix()" title="Download as CSV">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="fd-btn-close" onclick="closeFareMatrix()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        <div class="fd-matrix-body">
            <table class="fd-matrix-table">
                <thead>
                    <tr>
                        <th>Distance (km)</th>
                        <th>Regular Fare</th>
                        <th>Student / Senior / PWD</th>
                        <th>Savings</th>
                    </tr>
                </thead>
                <tbody id="fareMatrixBody">
                    <tr>
                        <td colspan="4" class="fd-loading-spinner" style="padding: 32px;">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <p style="margin:8px 0 0;font-size:13px;">Loading matrix...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Fare Guide Modal (Role-restricted for PICTO & Municipal) -->
    <div id="addFareGuideModal" class="fd-modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:16px;max-width:650px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 25px 50px -12px rgba(15,23,42,0.25);">
            <div style="display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg, #073B6B 0%, #0B5394 100%);padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.1);">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.18);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.15rem;box-shadow:inset 0 0 0 1px rgba(255,255,255,0.2);flex-shrink:0;">
                        <i class="fas fa-money-bill-trend-up"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:#fff;line-height:1.3;">Add Transportation Fare Guide</h3>
                        <p style="margin:3px 0 0 0;font-size:0.825rem;color:#93C5FD;font-weight:400;">Configure route details and import CSV fare matrix for your area</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddFareGuideModal()" style="width:32px;height:32px;background:rgba(255,255,255,0.15);border:none;border-radius:8px;color:#fff;font-size:0.95rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s ease;" onmouseover="this.style.background='rgba(255,255,255,0.28)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding:24px;max-height:calc(90vh - 90px);overflow-y:auto;">
                <form id="addFareGuideForm" onsubmit="submitFareGuideForm(event)">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Title / Route Name *</label>
                            <input type="text" id="fgTitle" required class="fd-search-input" style="width:100%;" placeholder="e.g. Tricycle Fare Matrix 2026">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Vehicle Type *</label>
                            <select id="fgVehicleType" required class="fd-filter-select" style="width:100%;" <?= ($userRole === 'municipal') ? 'disabled' : '' ?>>
                                <?php if ($userRole === 'municipal'): ?>
                                    <option value="Tricycle" selected>Tricycle (Municipal Only)</option>
                                <?php else: ?>
                                    <option value="Tricycle">Tricycle</option>
                                    <option value="PUJ_Ordinary">PUJ Ordinary (Jeepney)</option>
                                    <option value="PUJ_Aircon">PUJ Aircon (MPUJ)</option>
                                    <option value="PUB_Ordinary">PUB Ordinary (Bus)</option>
                                    <option value="PUB_Aircon">PUB Aircon (Bus)</option>
                                    <option value="Van">Van / UV Express</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div id="fgRegionWrap">
                            <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Region / Municipality *</label>
                            <?php if ($userRole === 'municipal'): 
                                $muniName = $_SESSION['user_municipality_name'] ?? 'Municipal';
                            ?>
                                <input type="text" id="fgRegion" required class="fd-search-input" style="width:100%; background:#f1f5f9; color:#475569;" value="<?= htmlspecialchars($muniName) ?>" readonly>
                                <p class="fd-region-hint" style="margin:4px 0 0;font-size:11px;color:#64748b;">
                                    <i class="fas fa-lock" style="font-size:10px;"></i> Restricted to your assigned municipality (<?= htmlspecialchars($muniName) ?>).
                                </p>
                            <?php else: ?>
                                <select id="fgRegion" required class="fd-filter-select" style="width:100%;">
                                    <option value="All Municipalities, La Union">All Municipalities, La Union</option>
                                    <option value="Agoo">Agoo</option>
                                    <option value="Aringay">Aringay</option>
                                    <option value="Bacnotan">Bacnotan</option>
                                    <option value="Bagulin">Bagulin</option>
                                    <option value="Balaoan">Balaoan</option>
                                    <option value="Bangar">Bangar</option>
                                    <option value="Bauang">Bauang</option>
                                    <option value="Burgos">Burgos</option>
                                    <option value="Caba">Caba</option>
                                    <option value="Luna">Luna</option>
                                    <option value="Naguilian">Naguilian</option>
                                    <option value="Pugo">Pugo</option>
                                    <option value="Rosario">Rosario</option>
                                    <option value="San Fernando">San Fernando</option>
                                    <option value="San Gabriel">San Gabriel</option>
                                    <option value="San Juan">San Juan</option>
                                    <option value="Santo Tomas">Santo Tomas</option>
                                    <option value="Santol">Santol</option>
                                    <option value="Sudipen">Sudipen</option>
                                    <option value="Tubao">Tubao</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Effective Date *</label>
                            <input type="date" id="fgEffectiveDate" required class="fd-search-input" style="width:100%;" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div style="margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">
                            <i class="fas fa-file-csv" style="color:#073B6B;margin-right:6px;"></i> Upload Fare Matrix CSV File *
                        </label>
                        <p style="margin:0 0 10px 0;font-size:12px;color:#64748b;">
                            CSV format: <strong>Distance (km)</strong>, <strong>Regular Fare (₱)</strong>, and optional <strong>Discounted (₱)</strong>.
                        </p>
                        <div style="border:2px dashed #cbd5e1;border-radius:8px;padding:20px 16px;text-align:center;background:#f8fafc;transition:border-color 0.2s;" ondragover="event.preventDefault()" ondrop="handleCsvDrop(event)">
                            <input type="file" id="fgCsvFile" accept=".csv" required style="display:none;" onchange="updateCsvFileInfo(this)">
                            <label for="fgCsvFile" style="cursor:pointer;display:block;">
                                <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:#073B6B;margin-bottom:6px;"></i>
                                <div style="font-size:13px;font-weight:600;color:#1e293b;" id="fgCsvFileName">Click or drag & drop to choose a .CSV file</div>
                                <span style="font-size:11px;color:#64748b;">Accepts .csv file format</span>
                            </label>
                        </div>
                        <div id="fgCsvPreview" style="margin-top:10px;font-size:12px;color:#059669;display:none;font-weight:600;background:#ecfdf5;padding:8px 12px;border-radius:6px;border:1px solid #a7f3d0;">
                            <i class="fas fa-check-circle"></i> <span id="fgCsvPreviewText"></span>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;border-top:1px solid #e2e8f0;padding-top:16px;">
                        <button type="button" onclick="closeAddFareGuideModal()" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;color:#475569;cursor:pointer;">Cancel</button>
                        <button type="submit" id="btnSubmitFareGuide" style="background:linear-gradient(135deg, #073B6B 0%, #0B5394 100%);border:none;padding:9px 22px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(7,59,107,0.3);"><i class="fas fa-save"></i> Save Fare Guide</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Floating Notification Toast Container -->
    <div class="fd-toast-container" id="fdToastContainer"></div>

</div>

<!-- Set user role context and load fare data API scripts -->
<script>
    window.userRole = '<?= htmlspecialchars($userRole ?? 'lupto') ?>';
    window.userMunicipalityName = <?= json_encode($_SESSION['user_municipality_name'] ?? null) ?>;
</script>
<script src="../scripts/api-config.js"></script>
<script src="../scripts/functions/fare-data-api.js"></script>

<?php
// Render content layout depending on AJAX SPA or direct page request
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) echo $extraHeadContent;
    echo $pageContent;
    exit;
}
include '../components/sections.php';

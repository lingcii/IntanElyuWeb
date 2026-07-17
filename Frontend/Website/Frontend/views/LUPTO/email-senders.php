<?php
require_once __DIR__ . '/../../session-bridge.php';
if ($_SESSION['user_role'] !== 'lupto' && $_SESSION['user_role'] !== 'picto') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Email Sender Accounts';
$muniName = $_SESSION['user_municipality_name'] ?? '';

ob_start();
?>
<link rel="stylesheet" href="../../css/LUPTO/email-senders.css">
<?php $extraHeadContent = ob_get_clean();

ob_start();
?>

<div class="es-page">
    <div class="es-header">
        <div>
            <h1><i class="fas fa-envelope"></i> Email Sender Accounts</h1>
            <p>Manage Gmail sender accounts for system notifications, password resets, and welcome emails.</p>
        </div>
        <button class="es-btn es-btn-primary" id="esAddBtn">
            <i class="fas fa-plus"></i> Add Sender
        </button>
    </div>

    <!-- Strategy Selector -->
    <div class="es-strategy-bar">
        <div class="es-strategy-label">Sending Strategy:</div>
        <select id="esStrategy" class="es-select">
            <option value="priority">Priority (try in order, fallback on fail)</option>
            <option value="round_robin">Round Robin (rotate evenly)</option>
            <option value="random">Random (pick any available sender)</option>
        </select>
    </div>

    <!-- Stats -->
    <div class="es-stats" id="esStats">
        <div class="es-stat-card">
            <div class="es-stat-value" id="esStatTotal">0</div>
            <div class="es-stat-label">Total Senders</div>
        </div>
        <div class="es-stat-card">
            <div class="es-stat-value" id="esStatActive">0</div>
            <div class="es-stat-label">Active</div>
        </div>
        <div class="es-stat-card">
            <div class="es-stat-value" id="esStatSent">0</div>
            <div class="es-stat-label">Total Emails Sent</div>
        </div>
    </div>

    <!-- Sender Cards -->
    <div class="es-grid" id="esGrid">
        <div class="es-empty" id="esEmpty">
            <i class="fas fa-envelope-open-text"></i>
            <h3>No sender accounts configured</h3>
            <p>Add a Gmail sender account to start sending emails.</p>
        </div>
    </div>

    <!-- Email Logs -->
    <div class="es-logs-section">
        <h2><i class="fas fa-history"></i> Recent Email Logs</h2>
        <div class="es-logs-table-wrap">
            <table class="es-logs-table">
                <thead>
                    <tr>
                        <th>Sender</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody id="esLogsBody">
                    <tr><td colspan="6" style="text-align:center;color:#9CA3AF;">Loading logs...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Sender Modal -->
<div class="modal" id="esFormModal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2 id="esFormTitle">Add Sender Account</h2>
            <button class="modal-close" id="esFormClose">&times;</button>
        </div>
        <div class="modal-body">
            <form id="esForm">
                <input type="hidden" id="esId">
                <div class="es-field">
                    <label>Display Name</label>
                    <input type="text" id="esName" placeholder="e.g., INTAN ELYU Admin" maxlength="100">
                </div>
                <div class="es-field">
                    <label>Gmail Address <span style="color:#DC2626;">*</span></label>
                    <input type="email" id="esEmail" placeholder="yourname@gmail.com" required>
                </div>
                <div class="es-field">
                    <label>Gmail App Password <span style="color:#DC2626;">*</span>
                        <a href="https://myaccount.google.com/apppasswords" target="_blank" style="font-size:11px;font-weight:400;margin-left:6px;">
                            Get App Password <i class="fas fa-external-link-alt"></i>
                        </a>
                    </label>
                    <input type="password" id="esPassword" placeholder="xxxx xxxx xxxx xxxx" autocomplete="off">
                    <span style="font-size:11px;color:#9CA3AF;">Leave blank when editing to keep current password.</span>
                </div>
                <div class="es-field">
                    <label>Priority</label>
                    <input type="number" id="esPriority" min="1" max="99" value="10" style="max-width:100px;">
                    <span style="font-size:11px;color:#9CA3AF;">Lower number = tried first.</span>
                </div>
                <div class="es-field">
                    <label class="es-checkbox-label">
                        <input type="checkbox" id="esIsDefault">
                        <span>Set as default sender</span>
                    </label>
                </div>
                <div id="esFormError" class="es-alert es-alert-error" style="display:none;"></div>
                <div class="es-form-btns">
                    <button type="button" class="es-btn es-btn-outline" id="esTestBtn">
                        <i class="fas fa-check-circle"></i> Test Connection
                    </button>
                    <button type="submit" class="es-btn es-btn-primary">
                        <i class="fas fa-save"></i> <span id="esSubmitLabel">Save Sender</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.ES_API_URL = new URL('../../api/email_senders.php', window.location.href).href;
</script>
<script src="../../scripts/functions/LUPTO/email-senders-api.js"></script>

<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) echo $extraHeadContent;
    echo $pageContent;
    exit;
}
include '../../components/sections.php';

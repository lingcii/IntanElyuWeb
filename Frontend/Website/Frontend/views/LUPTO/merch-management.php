<?php
require_once __DIR__ . '/../../session-bridge.php';
if ($_SESSION['user_role'] !== 'lupto') {
    header('Location: ../../login.php');
    exit;
}
$pageTitle = 'LUPTO – Merch Management';
ob_start();
?>
<link rel="stylesheet" href="../../css/LUPTO/merch-management.css?v=<?= time() ?>">
<?php
$extraHeadContent = ob_get_clean();
ob_start();
?>

<div class="merch-header">
    <h2><i class="fas fa-shirt"></i> Merch Management</h2>
    <button class="merch-btn merch-btn-primary" onclick="openAddMerchModal()"><i class="fas fa-plus"></i> Add New Item</button>
</div>

<div class="merch-tabs">
    <button class="merch-tab active" onclick="switchTab('inventory')" id="tab-inventory">Inventory</button>
    <button class="merch-tab" onclick="switchTab('reservations')" id="tab-reservations">Reservations</button>
</div>

<!-- INVENTORY TAB -->
<div id="view-inventory">
    <table class="merch-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Category</th>
                <th>Price (XP)</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="inventory-list">
            <tr><td colspan="6" style="text-align:center;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<!-- RESERVATIONS TAB -->
<div id="view-reservations" style="display:none;">
    <table class="merch-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Tourist Name</th>
                <th>Item</th>
                <th>Price (XP)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="reservations-list">
            <tr><td colspan="6" style="text-align:center;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<!-- ADD/EDIT MODAL -->
<div id="merch-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:white; padding:24px; border-radius:8px; width:100%; max-width:500px;">
        <h3 id="modal-title" style="margin-top:0;">Add Merchandise</h3>
        <form id="merch-form" onsubmit="saveMerch(event)">
            <input type="hidden" id="merch-id">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:4px; font-weight:500;">Title</label>
                <input type="text" id="merch-title" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            <div style="display:flex; gap:16px; margin-bottom:16px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500;">Category</label>
                    <select id="merch-category" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                        <option>Apparel</option>
                        <option>Accessories</option>
                        <option>Souvenirs</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500;">Badge (Optional)</label>
                    <input type="text" id="merch-badge" placeholder="e.g. Best Seller" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
            </div>
            <div style="display:flex; gap:16px; margin-bottom:16px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500;">Price (XP)</label>
                    <input type="number" id="merch-price" required min="0" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:4px; font-weight:500;">Stock</label>
                    <input type="number" id="merch-stock" required min="0" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:4px; font-weight:500;">Image URL</label>
                <input type="url" id="merch-image" placeholder="https://..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="merch-btn" onclick="closeMerchModal()">Cancel</button>
                <button type="submit" class="merch-btn merch-btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script src="../../scripts/functions/LUPTO/merch-management.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../layouts/main.php';
?>

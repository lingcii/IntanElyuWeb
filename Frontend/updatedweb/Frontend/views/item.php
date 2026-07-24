<?php
require_once __DIR__ . '/../session-bridge.php';
// Check role
if ($_SESSION['user_role'] !== 'municipal' && !str_ends_with($_SESSION['user_role'], '_mto')) {
    header('Location: ../login.php');
    exit;
}
$pageTitle = 'Item';

ob_start();
?>
    <h2 class="section-title">Item</h2>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Overview</h3>
        </div>
        <div class="card-body">
            <p>Item content will go here.</p>
        </div>
    </div>
<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}
include '../components/sections.php';

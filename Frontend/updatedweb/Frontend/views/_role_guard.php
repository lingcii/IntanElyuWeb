<?php

//  _role_guard.php
//   Shared role guard helper.
//   Expects the calling stub to have set:
//     $allowedRoles  — array of allowed role strings, e.g. ['lupto']
//     $loginRedirect — (optional) path to login.php, defaults to ../../login.php
//   Usage in a stub:
//     $allowedRoles = ['lupto'];
//     require_once __DIR__ . '/_role_guard.php';


if (!isset($_SESSION) || session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_rg_role     = $_SESSION['user_role'] ?? '';
$_rg_allowed  = $allowedRoles ?? [];
$_rg_redirect = $loginRedirect ?? '../login.php';

// Determine the role family (lupto, picto/pitco, municipal/*_mto)
function _rg_is_municipal(string $role): bool {
    return $role === 'municipal' || str_ends_with($role, '_mto');
}

function _rg_is_picto(string $role): bool {
    return in_array($role, ['picto', 'pitco'], true);
}

$_rg_pass = false;
foreach ($_rg_allowed as $_rg_check) {
    if ($_rg_check === 'municipal') {
        if (_rg_is_municipal($_rg_role)) { $_rg_pass = true; break; }
    } elseif ($_rg_check === 'picto') {
        if (_rg_is_picto($_rg_role)) { $_rg_pass = true; break; }
    } else {
        if ($_rg_role === $_rg_check) { $_rg_pass = true; break; }
    }
}

if (!$_rg_pass) {
    header('Location: ' . $_rg_redirect);
    exit;
}

// Expose a clean $userRole variable for use in shared templates
// Normalise picto/pitco → 'picto', any *_mto → 'municipal'
if (_rg_is_picto($_rg_role)) {
    $userRole = 'picto';
} elseif (_rg_is_municipal($_rg_role)) {
    $userRole = 'municipal';
} else {
    $userRole = $_rg_role; // 'lupto' or any other explicit value
}

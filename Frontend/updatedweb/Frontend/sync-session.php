<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['clear_just_logged_in'])) {
        unset($_SESSION['just_logged_in']);
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($input['clear_must_change_password'])) {
        unset($_SESSION['must_change_password']);
        unset($_SESSION['just_logged_in']);
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($input['user'])) {
        $_SESSION['user_id'] = $input['user']['id'];
        $_SESSION['user_name'] = $input['user']['name'];
        $_SESSION['user_email'] = $input['user']['email'];
        $_SESSION['user_role'] = $input['user']['role'];
        $_SESSION['user_municipality_id'] = $input['user']['municipality_id'] ?? null;
        $_SESSION['user_municipality_name'] = $input['user']['municipality_name'] ?? null;
        $_SESSION['must_change_password'] = $input['user']['must_change_password'] ?? false;
        if ($_SESSION['must_change_password']) {
            $_SESSION['just_logged_in'] = true;
        }

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);

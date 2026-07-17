<?php
/**
 * upload-spot-image.php
 *
 * Saves uploaded spot images LOCALLY into Frontend/images/tourist_spots/
 * instead of proxying to the Laravel backend storage folder.
 *
 * This avoids Windows/OneDrive ACL permission errors on backend/storage.
 */

require_once __DIR__ . '/../session-bridge.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require a valid session
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised. Please log in again.']);
    exit;
}

// Validate file upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $phpErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];
    $code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg  = $phpErrors[$code] ?? "Upload error code $code";
    http_response_code(422);
    echo json_encode(['error' => $msg, 'code' => $code]);
    exit;
}

$file = $_FILES['image'];

// Validate MIME type using finfo (not relying on browser-supplied type)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/jpg', 'image/png'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid file type. Only JPEG and PNG are allowed.']);
    exit;
}

// Max 10 MB
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['error' => 'File is too large. Maximum size is 10 MB.']);
    exit;
}

// ── Determine the safe local upload directory ──────────────────────────────
// Stored in: Frontend/Website/Frontend/images/tourist_spots/
// Served by serve-image.php which already checks __DIR__ . '/../images/tourist_spots/'
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'images';
$baseDir = realpath($baseDir) ?: $baseDir;
$uploadDir = $baseDir . DIRECTORY_SEPARATOR . 'tourist_spots' . DIRECTORY_SEPARATOR;

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create upload directory.']);
        exit;
    }
}

// Build a unique filename
$origExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeExt  = in_array($origExt, ['jpg', 'jpeg', 'png'], true) ? $origExt : 'jpg';
$filename = 'spot_' . uniqid() . '.' . $safeExt;
$destPath = $uploadDir . $filename;

// Move the uploaded temp file to its final location
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save the uploaded file. Check folder permissions.']);
    exit;
}

// The frontend serve-image.php checks ../images/tourist_spots/ so this URL works
$photoUrl = '/api/serve-image.php?file=' . urlencode($filename);

http_response_code(200);
echo json_encode([
    'success'   => true,
    'photo_url' => $photoUrl,
    'filename'  => $filename,
]);

<?php
/**
 * Proxy script to serve images from multiple possible directories
 * without requiring a symlink or admin privileges.
 */

// Get the filename from the query string
$filename = $_GET['file'] ?? '';

// Basic security: only allow alphanumeric, dots, dashes, underscores
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(400);
    exit('Invalid filename');
}

// List of possible directories to check for the image
// __DIR__ = .../Frontend/Website/Frontend/api
// Primary: local Frontend images folder (avoids OneDrive ACL issues on backend/storage)
// Fallbacks: legacy backend storage paths for older uploaded images
$directories = [
    __DIR__ . '/../images/tourist_spots/',                              // primary upload location
    __DIR__ . '/../images/',                                            // fallback: bare images dir
    __DIR__ . '/../../../../backend/storage/app/public/tourist_spots/', // Laravel storage fallback
    __DIR__ . '/../../../../backend/storage/app/public/',
    __DIR__ . '/../../../../backend/public/storage/tourist_spots/',
    __DIR__ . '/../../../../backend/public/uploads/tourist_spots/',
];

$imagePath = null;

// Check each directory for the file
foreach ($directories as $dir) {
    $testPath = $dir . $filename;
    if (file_exists($testPath)) {
        $imagePath = $testPath;
        break;
    }
}

// If no file found, return 404
if (!$imagePath) {
    http_response_code(404);
    exit('File not found');
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $imagePath);
finfo_close($finfo);

// Serve the file
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($imagePath));
header('Cache-Control: public, max-age=31536000');
readfile($imagePath);
exit;

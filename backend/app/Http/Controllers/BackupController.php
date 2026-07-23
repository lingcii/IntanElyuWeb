<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function backupDir(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function isMunicipal(): bool
    {
        $role = Session::get('user_role', '');
        return $role === 'municipal' || str_ends_with($role, '_mto');
    }

    private function getMunicipalitySlug(): string
    {
        $municipality = Session::get('user_municipality', '');
        return Str::slug($municipality ?: 'municipality');
    }

    /**
     * Validate a backup filename: alphanumeric, underscores, hyphens, ends in .sql
     * Municipal: must start with backup_{slug}_
     */
    private function validateFilename(string $filename): bool
    {
        if (!preg_match('/^backup_[a-zA-Z0-9_\-]+\.sql$/', $filename)) {
            return false;
        }
        if ($this->isMunicipal()) {
            $slug = $this->getMunicipalitySlug();
            return str_starts_with($filename, "backup_{$slug}_");
        }
        return true;
    }

    /**
     * List .sql files in the backup dir, optionally filtered by municipality slug.
     */
    private function getBackupFiles(): array
    {
        $dir = $this->backupDir();
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];

        $result = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (!preg_match('/^backup_[a-zA-Z0-9_\-]+\.sql$/', $name)) {
                continue;
            }
            // Municipal scope filter
            if ($this->isMunicipal()) {
                $slug = $this->getMunicipalitySlug();
                if (!str_starts_with($name, "backup_{$slug}_")) {
                    continue;
                }
            }
            $size     = filesize($path);
            $modified = filemtime($path);
            $result[] = [
                'filename'  => $name,
                'size'      => $size,
                'size_fmt'  => $this->formatBytes($size),
                'date'      => date('Y-m-d H:i:s', $modified),
                'date_fmt'  => date('M d, Y h:i A', $modified),
                'timestamp' => $modified,
            ];
        }

        // Sort newest first
        usort($result, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
        return $result;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /backup/stats
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $files      = $this->getBackupFiles();
        $total      = count($files);
        $lastBackup = $total > 0 ? $files[0]['date_fmt'] : null;

        // DB size
        $dbName  = config('database.connections.mysql.database');
        $dbSize  = '–';
        try {
            $row = DB::selectOne(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.TABLES
                 WHERE table_schema = ?",
                [$dbName]
            );
            if ($row && $row->size_mb !== null) {
                $dbSize = $row->size_mb . ' MB';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'success'      => true,
            'total'        => $total,
            'last_backup'  => $lastBackup ?? 'Never',
            'db_size'      => $dbSize,
            'status'       => 'Healthy',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /backup/list
    // ─────────────────────────────────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'backups' => $this->getBackupFiles(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /backup/create
    // ─────────────────────────────────────────────────────────────────────────

    public function create(Request $request): JsonResponse
    {
        $timestamp = date('Ymd_His');
        $dir       = $this->backupDir();

        if ($this->isMunicipal()) {
            $slug     = $this->getMunicipalitySlug();
            $filename = "backup_{$slug}_{$timestamp}.sql";
        } else {
            $filename = "backup_{$timestamp}.sql";
        }

        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

        $success = $this->isMunicipal()
            ? $this->dumpMunicipalDatabase($filepath)
            : $this->dumpFullDatabase($filepath);

        if (!$success || !file_exists($filepath) || filesize($filepath) === 0) {
            if (file_exists($filepath)) unlink($filepath);
            return response()->json([
                'success' => false,
                'message' => 'Database backup failed. Please try again.',
            ], 500);
        }

        ActivityLogService::log(
            ActivityAction::BACKUP_CREATED,
            'Backup',
            'Database backup created: ' . $filename,
            null,
            ['filename' => $filename, 'size' => $this->formatBytes(filesize($filepath))],
            $request
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Database backup created successfully.',
            'filename' => $filename,
            'size'     => $this->formatBytes(filesize($filepath)),
            'date'     => date('M d, Y h:i A'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /backup/download/{filename}
    // ─────────────────────────────────────────────────────────────────────────

    public function download(Request $request, string $filename): StreamedResponse|JsonResponse
    {
        $filename = basename($filename);

        if (!$this->validateFilename($filename)) {
            return response()->json(['success' => false, 'message' => 'Access denied or invalid file.'], 403);
        }

        $filepath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filepath)) {
            return response()->json(['success' => false, 'message' => 'Backup file not found.'], 404);
        }

        return response()->streamDownload(function () use ($filepath) {
            readfile($filepath);
        }, $filename, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => filesize($filepath),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /backup/restore  (from filename in body OR uploaded file)
    // ─────────────────────────────────────────────────────────────────────────

    public function restoreFromFile(Request $request): JsonResponse
    {
        // Case 1: restore by filename (from the Recent Backups table)
        if ($request->filled('filename')) {
            $filename = basename($request->input('filename'));
            if (!$this->validateFilename($filename)) {
                return response()->json(['success' => false, 'message' => 'Access denied or invalid file.'], 403);
            }
            $filepath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($filepath)) {
                return response()->json(['success' => false, 'message' => 'Backup file not found.'], 404);
            }
        }
        // Case 2: file upload
        elseif ($request->hasFile('backup_file')) {
            $file = $request->file('backup_file');
            if ($file->getClientOriginalExtension() !== 'sql') {
                return response()->json(['success' => false, 'message' => 'Invalid SQL file. Only .sql files are allowed.'], 422);
            }
            // For municipal: validate the uploaded filename matches their slug
            $uploadedName = $file->getClientOriginalName();
            if ($this->isMunicipal()) {
                $slug = $this->getMunicipalitySlug();
                if (!str_starts_with($uploadedName, "backup_{$slug}_")) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only restore backup files from your own municipality.',
                    ], 403);
                }
            }
            // Store temporarily
            $filename = 'restore_tmp_' . time() . '.sql';
            $filepath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;
            $file->move($this->backupDir(), $filename);
        } else {
            return response()->json(['success' => false, 'message' => 'No backup file provided.'], 422);
        }

        // Validate it looks like SQL
        $firstLine = '';
        $handle = fopen($filepath, 'r');
        if ($handle) {
            $firstLine = fgets($handle);
            fclose($handle);
        }
        if (empty(trim($firstLine)) || stripos($firstLine . file_get_contents($filepath, false, null, 0, 512), 'sql') === false) {
            // Not a valid SQL file — check for SQL keywords
            $sample = file_get_contents($filepath, false, null, 0, 1024);
            $hasSql = preg_match('/\b(CREATE|INSERT|DROP|ALTER|SELECT)\b/i', $sample);
            if (!$hasSql) {
                if (isset($filename) && str_starts_with($filename, 'restore_tmp_')) {
                    unlink($filepath);
                }
                return response()->json(['success' => false, 'message' => 'Invalid SQL file. The file does not appear to be a valid database backup.'], 422);
            }
        }

        $result = $this->importSqlFile($filepath);

        // Clean up temp upload
        if (isset($filename) && str_starts_with($filename, 'restore_tmp_')) {
            if (file_exists($filepath)) unlink($filepath);
        }

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Restore failed: ' . $result['error'],
            ], 500);
        }

        ActivityLogService::log(
            ActivityAction::BACKUP_RESTORED,
            'Backup',
            'Database restored from: ' . basename($filepath),
            null,
            ['filename' => basename($filepath)],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Database restored successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DELETE /backup/{filename}
    // ─────────────────────────────────────────────────────────────────────────

    public function delete(Request $request, string $filename): JsonResponse
    {
        $filename = basename($filename);

        if (!$this->validateFilename($filename)) {
            return response()->json(['success' => false, 'message' => 'Access denied or invalid file.'], 403);
        }

        $filepath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filepath)) {
            return response()->json(['success' => false, 'message' => 'Backup file not found.'], 404);
        }

        unlink($filepath);

        ActivityLogService::log(
            ActivityAction::BACKUP_DELETED,
            'Backup',
            'Backup file deleted: ' . $filename,
            ['filename' => $filename],
            null,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Backup deleted successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dump: Full database (LUPTO / PICTO)
    // ─────────────────────────────────────────────────────────────────────────

    private function dumpFullDatabase(string $filepath): bool
    {
        $cfg  = config('database.connections.mysql');
        $host = $cfg['host'];
        $port = $cfg['port'] ?? 3306;
        $db   = $cfg['database'];
        $user = $cfg['username'];
        $pass = $cfg['password'];

        // Try mysqldump first
        if ($this->mysqldumpAvailable()) {
            $passArg = $pass ? "-p" . escapeshellarg($pass) : '';
            $cmd = sprintf(
                'mysqldump --host=%s --port=%s --user=%s %s --single-transaction --routines --triggers --no-tablespaces %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg((string)$port),
                escapeshellarg($user),
                $passArg,
                escapeshellarg($db),
                escapeshellarg($filepath)
            );
            exec($cmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                return true;
            }
        }

        // PDO fallback
        return $this->pdoDump($filepath);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dump: Municipality-scoped (MUNICIPAL)
    // ─────────────────────────────────────────────────────────────────────────

    private function dumpMunicipalDatabase(string $filepath): bool
    {
        $municipalityId = Session::get('user_municipality_id');
        if (!$municipalityId) {
            return false;
        }

        return $this->pdoMunicipalDump($filepath, (int)$municipalityId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PDO full dump
    // ─────────────────────────────────────────────────────────────────────────

    private function pdoDump(string $filepath): bool
    {
        try {
            $pdo    = DB::getPdo();
            $dbName = config('database.connections.mysql.database');
            $handle = fopen($filepath, 'w');
            if (!$handle) return false;

            fwrite($handle, "-- Database backup generated by Intan-Elyu System\n");
            fwrite($handle, "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $table = '`' . $table . '`';

                // Create table
                $row = $pdo->query("SHOW CREATE TABLE {$table}")->fetch(\PDO::FETCH_ASSOC);
                $createSql = array_values($row)[1] ?? '';
                fwrite($handle, "DROP TABLE IF EXISTS {$table};\n");
                fwrite($handle, $createSql . ";\n\n");

                // Data
                $rows = $pdo->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
                if (empty($rows)) continue;

                $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $chunks = array_chunk($rows, 100);
                foreach ($chunks as $chunk) {
                    $values = array_map(function ($row) use ($pdo) {
                        return '(' . implode(', ', array_map(function ($val) use ($pdo) {
                            return $val === null ? 'NULL' : $pdo->quote((string)$val);
                        }, array_values($row))) . ')';
                    }, $chunk);
                    fwrite($handle, "INSERT INTO {$table} ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");
                }
                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PDO municipality-scoped dump
    // ─────────────────────────────────────────────────────────────────────────

    private function pdoMunicipalDump(string $filepath, int $municipalityId): bool
    {
        try {
            $pdo    = DB::getPdo();
            $handle = fopen($filepath, 'w');
            if (!$handle) return false;

            $municipalityName = Session::get('user_municipality', 'Municipality');

            fwrite($handle, "-- Municipality backup: {$municipalityName} (ID: {$municipalityId})\n");
            fwrite($handle, "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            // Tables scoped by municipality_id
            $municipalScoped = [
                'tourist_spots',
                'tourist_spot_images',
                'tourist_spot_audit',
                'fare_guides',
                'fare_matrices',
                'fare_uploads',
                'import_logs',
                'validation_errors',
                'analytics',
                'activity_logs',
                'notifications',
            ];

            // Tables to dump fully (reference/lookup data)
            $referenceTables = [
                'municipalities',
            ];

            // Dump reference tables fully
            foreach ($referenceTables as $tableName) {
                $table = '`' . $tableName . '`';
                $tableExists = $pdo->query("SHOW TABLES LIKE '{$tableName}'")->rowCount() > 0;
                if (!$tableExists) continue;

                $row = $pdo->query("SHOW CREATE TABLE {$table}")->fetch(\PDO::FETCH_ASSOC);
                $createSql = array_values($row)[1] ?? '';
                fwrite($handle, "-- Table: {$tableName} (reference)\n");
                fwrite($handle, "DROP TABLE IF EXISTS {$table};\n");
                fwrite($handle, $createSql . ";\n\n");

                $rows = $pdo->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                    $chunks  = array_chunk($rows, 100);
                    foreach ($chunks as $chunk) {
                        $values = array_map(function ($row) use ($pdo) {
                            return '(' . implode(', ', array_map(fn($val) => $val === null ? 'NULL' : $pdo->quote((string)$val), array_values($row))) . ')';
                        }, $chunk);
                        fwrite($handle, "INSERT INTO {$table} ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");
                    }
                    fwrite($handle, "\n");
                }
            }

            // Dump scoped tables
            foreach ($municipalScoped as $tableName) {
                $table = '`' . $tableName . '`';
                $tableExists = $pdo->query("SHOW TABLES LIKE '{$tableName}'")->rowCount() > 0;
                if (!$tableExists) continue;

                // Check if municipality_id column exists
                $cols = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(\PDO::FETCH_COLUMN);
                $hasScope = in_array('municipality_id', $cols);

                $row = $pdo->query("SHOW CREATE TABLE {$table}")->fetch(\PDO::FETCH_ASSOC);
                $createSql = array_values($row)[1] ?? '';
                fwrite($handle, "-- Table: {$tableName}" . ($hasScope ? " (scoped to municipality {$municipalityId})" : "") . "\n");
                fwrite($handle, "DROP TABLE IF EXISTS {$table};\n");
                fwrite($handle, $createSql . ";\n\n");

                if ($hasScope) {
                    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE municipality_id = ?");
                    $stmt->execute([$municipalityId]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } else {
                    $rows = $pdo->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
                }

                if (!empty($rows)) {
                    $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                    $chunks  = array_chunk($rows, 100);
                    foreach ($chunks as $chunk) {
                        $values = array_map(function ($row) use ($pdo) {
                            return '(' . implode(', ', array_map(fn($val) => $val === null ? 'NULL' : $pdo->quote((string)$val), array_values($row))) . ')';
                        }, $chunk);
                        fwrite($handle, "INSERT INTO {$table} ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");
                    }
                    fwrite($handle, "\n");
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Import SQL file
    // ─────────────────────────────────────────────────────────────────────────

    private function importSqlFile(string $filepath): array
    {
        try {
            $sql = file_get_contents($filepath);
            if ($sql === false) {
                return ['success' => false, 'error' => 'Could not read backup file.'];
            }

            DB::unprepared($sql);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function mysqldumpAvailable(): bool
    {
        if (!function_exists('exec')) return false;
        exec('mysqldump --version 2>&1', $out, $code);
        return $code === 0;
    }
}

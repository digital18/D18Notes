<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Password (bcrypt hash) ────────────────────────────────────────────────────
// Plain text password is NEVER stored here.
// To generate a new hash, open make_hash.php in your browser once, then delete it.
//
// Current hash is for: Bhavesh@18
define('APP_PASSWORD_HASH', '$2y$12$placeholder_run_make_hash_php');

// ── Encryption settings ───────────────────────────────────────────────────────
// ENCRYPT_SECRET: change this to any long random phrase — keep it secret.
// This is used to derive the AES-256 key for the data file.
define('ENCRYPT_SECRET', 'D18Notes@Bhavesh#SecretPhrase2024!ChangeThis');

// ── Data file path ────────────────────────────────────────────────────────────
// Stored in the same folder as index.php, encrypted so it cannot be read directly.
define('DATA_FILE', __DIR__ . '/notes.dat');

// ── Note bubble alignment ─────────────────────────────────────────────────────
// 'left'  — bubbles align to the left (document/diary style)
// 'right' — bubbles align to the right (chat/WhatsApp style)
define('BUBBLE_ALIGN', 'left');

// ── Default app theme ─────────────────────────────────────────────────────────
// Sets the theme shown to a user on their first visit (before they toggle).
// Users can always switch with the 🌙/☀️ button — preference is saved in localStorage.
// 'light' — clean white interface
// 'dark'  — dark navy interface
define('APP_THEME', 'light');

// ── Accent color ──────────────────────────────────────────────────────────────
// Drives buttons, links, search highlights, badges — any accent element.
// Examples: '#6c63ff' (purple), '#0ea5e9' (sky blue), '#10b981' (emerald), '#f59e0b' (amber)
define('ACCENT_COLOR', '#6c63ff');

// ── Note bubble appearance ────────────────────────────────────────────────────
// Controls the look of individual note bubbles (NOT the app chrome).
define('BUBBLE_BG',   '#ffffff');   // bubble background color
define('BUBBLE_TEXT', '#1e1e2e');  // bubble text color

// ── Media uploads ─────────────────────────────────────────────────────────────
define('MEDIA_DIR',     __DIR__ . '/media');
define('MAX_FILE_SIZE', 20 * 1024 * 1024);   // 20 MB per file
define('MAX_FILES',     5);                   // max attachments per note
define('ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv', 'application/json',
]);

// ── Derive 32-byte AES-256 key from passphrase ────────────────────────────────
function getEncryptKey(): string {
    return hash('sha256', ENCRYPT_SECRET, true); // 32 raw bytes
}

// ── Encrypt JSON string → storable string ─────────────────────────────────────
function encryptData(string $plaintext): string {
    $iv         = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', getEncryptKey(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv) . ':' . base64_encode($ciphertext);
}

// ── Decrypt stored string → JSON string ───────────────────────────────────────
function decryptData(string $stored): string {
    $parts = explode(':', $stored, 2);
    if (count($parts) !== 2) return '[]';
    $iv         = base64_decode($parts[0]);
    $ciphertext = base64_decode($parts[1]);
    $plain      = openssl_decrypt($ciphertext, 'AES-256-CBC', getEncryptKey(), OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '[]';
}

// ── Load all notes from encrypted file ───────────────────────────────────────
function loadNotes(): array {
    if (!file_exists(DATA_FILE) || filesize(DATA_FILE) === 0) return [];
    $raw  = file_get_contents(DATA_FILE);
    $json = decryptData(trim($raw));
    return json_decode($json, true) ?? [];
}

// ── Save notes array to encrypted file ───────────────────────────────────────
function saveNotes(array $notes): void {
    $json = json_encode(array_values($notes), JSON_UNESCAPED_UNICODE);
    file_put_contents(DATA_FILE, encryptData($json), LOCK_EX);
}

// ── Fetch notes sorted oldest → newest ───────────────────────────────────────
function fetchNotes(): array {
    $notes = loadNotes();
    usort($notes, function($a, $b) {
        return strcmp($a['DateTime'], $b['DateTime']);
    });
    return $notes;
}

// ── Insert a new note ─────────────────────────────────────────────────────────
function insertNote(string $note, string $ip, string $browser, string $location, array $attachments = []): void {
    $notes = loadNotes();
    $maxId = empty($notes) ? 0 : max(array_column($notes, 'Id'));
    $entry = [
        'Id'       => $maxId + 1,
        'Note'     => $note,
        'DateTime' => date('Y-m-d H:i:s'),
        'IP'       => $ip,
        'Browser'  => $browser,
        'Location' => $location,
        'Category' => 'general',
    ];
    if (!empty($attachments)) {
        $entry['Attachments'] = $attachments;
    }
    $notes[] = $entry;
    saveNotes($notes);
}

// ── Delete a note by Id (also removes attached media files) ──────────────────
function deleteNote(int $id): void {
    $notes = loadNotes();
    foreach ($notes as $n) {
        if ((int)$n['Id'] === $id) {
            foreach ($n['Attachments'] ?? [] as $att) {
                $path = MEDIA_DIR . '/' . ($att['filename'] ?? '');
                if (!empty($att['filename']) && file_exists($path)) {
                    @unlink($path);
                }
            }
            break;
        }
    }
    $notes = array_values(array_filter($notes, function($n) use ($id) {
        return (int)$n['Id'] !== $id;
    }));
    saveNotes($notes);
}

// ── Update a note's text and/or datetime ─────────────────────────────────────
function updateNote(int $id, string $note, string $dateTime): void {
    $notes = loadNotes();
    foreach ($notes as &$n) {
        if ((int)$n['Id'] === $id) {
            $n['Note']     = $note;
            $n['DateTime'] = $dateTime;
            break;
        }
    }
    unset($n);
    saveNotes($notes);
}

// ── Toggle a note's category between 'general' and 'star' ────────────────────
function setNoteCategory(int $id, string $category): string {
    $allowed = ['general', 'star'];
    if (!in_array($category, $allowed, true)) return 'general';
    $notes  = loadNotes();
    $newCat = $category;
    foreach ($notes as &$n) {
        if ((int)$n['Id'] === $id) {
            $n['Category'] = $category;
            break;
        }
    }
    unset($n);
    saveNotes($notes);
    return $newCat;
}

// ── Ensure media/ directory exists with PHP-execution blocked ────────────────
function ensureMediaDir(): void {
    if (!is_dir(MEDIA_DIR)) {
        mkdir(MEDIA_DIR, 0755, true);
    }
    $htaccess = MEDIA_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess,
            "Options -Indexes -ExecCGI\n" .
            "<FilesMatch \"\\.php$\">\n" .
            "  Require all denied\n" .
            "</FilesMatch>\n"
        );
    }
}

// ── Upload a single file to media/ and return its metadata ───────────────────
function uploadMedia(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder on server',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        ];
        throw new RuntimeException($msgs[$file['error']] ?? 'Upload error #' . $file['error']);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('File too large (max ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB)');
    }

    // Detect real MIME from file content, not from the browser header
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_MIME, true)) {
        throw new RuntimeException('File type not allowed: ' . $mime);
    }

    static $extMap = [
        'image/jpeg'   => 'jpg',  'image/png'  => 'png',
        'image/gif'    => 'gif',  'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain'   => 'txt',  'text/csv'  => 'csv',
        'application/json' => 'json',
    ];

    $ext      = $extMap[$mime] ?? 'bin';
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;

    ensureMediaDir();

    if (!move_uploaded_file($file['tmp_name'], MEDIA_DIR . '/' . $filename)) {
        throw new RuntimeException('Failed to save uploaded file');
    }

    return [
        'filename' => $filename,
        'original' => mb_substr(basename($file['name']), 0, 200),
        'mime'     => $mime,
        'size'     => (int)$file['size'],
    ];
}

// ── File display helpers ──────────────────────────────────────────────────────
function fileIconEmoji(string $mime): string {
    if (strpos($mime, 'image/') === 0)                       return '🖼';
    if ($mime === 'application/pdf')                         return '📄';
    if (strpos($mime, 'word') !== false)                     return '📝';
    if (strpos($mime, 'excel') !== false || strpos($mime, 'spreadsheet') !== false || $mime === 'text/csv') return '📊';
    if ($mime === 'text/plain')                              return '📃';
    if ($mime === 'application/json')                        return '🔧';
    return '📎';
}

function formatFileSize(int $bytes): string {
    if ($bytes < 1024)               return $bytes . ' B';
    if ($bytes < 1024 * 1024)       return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

// ── Verify login password ─────────────────────────────────────────────────────
function checkPassword(string $input): bool {
    // If hash is not yet generated (placeholder), fall back to plain match temporarily
    if (strpos(APP_PASSWORD_HASH, 'placeholder') !== false) {
        return $input === 'Bhavesh@18'; // Remove once make_hash.php is run
    }
    return password_verify($input, APP_PASSWORD_HASH);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function getClientIP(): string {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    $ip = trim($ip);
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function getBrowser(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/Edg\//i',        $ua)) return 'Microsoft Edge';
    if (preg_match('/OPR\//i',        $ua)) return 'Opera';
    if (preg_match('/Firefox/i',      $ua)) return 'Firefox';
    if (preg_match('/Chrome/i',       $ua)) return 'Chrome';
    if (preg_match('/Safari/i',       $ua)) return 'Safari';
    if (preg_match('/MSIE|Trident/i', $ua)) return 'Internet Explorer';
    return $ua ? mb_substr($ua, 0, 100) : 'Unknown';
}

function getLocation(string $ip): string {
    if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) return 'Localhost';

    if (ini_get('allow_url_fopen')) {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 4]]);
            $raw = @file_get_contents(
                "http://ip-api.com/json/{$ip}?fields=status,city,regionName,country", false, $ctx
            );
            if ($raw) {
                $data = json_decode($raw, true);
                if (($data['status'] ?? '') === 'success') {
                    $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
                    return implode(', ', $parts) ?: 'Unknown';
                }
            }
        } catch (Throwable $e) { /* fall through */ }
    }

    if (function_exists('curl_init')) {
        try {
            $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4]);
            $raw = curl_exec($ch);
            curl_close($ch);
            if ($raw) {
                $data = json_decode($raw, true);
                if (($data['status'] ?? '') === 'success') {
                    $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
                    return implode(', ', $parts) ?: 'Unknown';
                }
            }
        } catch (Throwable $e) { /* fall through */ }
    }

    return 'Unknown';
}

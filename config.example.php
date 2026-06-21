<?php
// ══════════════════════════════════════════════════════════════════════════════
//  config.example.php — D18 Notes configuration template
//
//  SETUP INSTRUCTIONS:
//  1. Copy this file:  cp config.example.php config.php
//  2. Generate a bcrypt password hash by opening make_hash.php in your browser
//  3. Paste the hash into APP_PASSWORD_HASH below
//  4. Change ENCRYPT_SECRET to any long random phrase you'll remember
//  5. Delete make_hash.php from your server after use
// ══════════════════════════════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Password (bcrypt hash) ────────────────────────────────────────────────────
// Generate your hash: open make_hash.php in browser → enter password → copy hash
// Never store your plain-text password here.
define('APP_PASSWORD_HASH', '');   // ← Paste your bcrypt hash here

// ── Encryption secret ─────────────────────────────────────────────────────────
// Any long, random passphrase. Used to derive the AES-256 key for notes.dat.
// ⚠️  Changing this AFTER you have notes will make them unreadable.
// ⚠️  Keep this secret — do not commit the real value to version control.
define('ENCRYPT_SECRET', 'change-this-to-a-long-random-phrase-you-will-remember');

// ── Data file path ────────────────────────────────────────────────────────────
// Where your encrypted notes are stored. Default: same folder as index.php.
// Change to an absolute path outside webroot for extra security, e.g.:
//   define('DATA_FILE', '/home/yourusername/private/notes.dat');
define('DATA_FILE', __DIR__ . '/notes.dat');

// ── Note bubble alignment ─────────────────────────────────────────────────────
// Controls which side note bubbles appear on.
// 'left'  — left-aligned bubbles (document/diary style)
// 'right' — right-aligned bubbles (chat/WhatsApp style)
define('BUBBLE_ALIGN', 'left');

// ── Default app theme ─────────────────────────────────────────────────────────
// Sets the theme on first visit. Users toggle with 🌙/☀️; choice saved in localStorage.
// 'light' — white interface  |  'dark' — dark navy interface
define('APP_THEME', 'light');

// ── Accent color ──────────────────────────────────────────────────────────────
// Drives buttons, links, search highlights, badges.
// Examples: '#6c63ff' (purple), '#0ea5e9' (sky blue), '#10b981' (emerald), '#f59e0b' (amber)
define('ACCENT_COLOR', '#6c63ff');

// ── Note bubble appearance ────────────────────────────────────────────────────
define('BUBBLE_BG',   '#ffffff');   // bubble background color
define('BUBBLE_TEXT', '#1e1e2e');  // bubble text color

// ── Media uploads ─────────────────────────────────────────────────────────────
define('MEDIA_DIR',     __DIR__ . '/media');
define('MAX_FILE_SIZE', 20 * 1024 * 1024);   // 20 MB per file
define('MAX_FILES',     5);                   // max attachments per note
define('ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'image/bmp', 'image/tiff', 'image/x-bmp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv', 'application/json',
]);

// ══════════════════════════════════════════════════════════════════════════════
//  Everything below this line is application logic — no need to change.
// ══════════════════════════════════════════════════════════════════════════════

// ── Derive 32-byte AES-256 key from passphrase ────────────────────────────────
function getEncryptKey(): string {
    return hash('sha256', ENCRYPT_SECRET, true);
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
    foreach ($notes as &$n) {
        if ((int)$n['Id'] === $id) {
            $n['Category'] = $category;
            break;
        }
    }
    unset($n);
    saveNotes($notes);
    return $category;
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

// ── Detect MIME type with fallbacks (fileinfo ext → mime_content_type → browser) ─
function detectMimeType(string $path, string $browserType = ''): string {
    if (class_exists('finfo', false)) {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
            if ($mime !== false && $mime !== '') return $mime;
        } catch (\Throwable $ignored) {}
    }
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if ($mime !== false && $mime !== '') return $mime;
    }
    return $browserType; // still allowlist-checked below
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
    $mime = detectMimeType($file['tmp_name'], $file['type']);
    if (!in_array($mime, ALLOWED_MIME, true)) {
        throw new RuntimeException('File type not allowed: ' . $mime);
    }
    static $extMap = [
        'image/jpeg'  => 'jpg',  'image/png'  => 'png',  'image/gif'  => 'gif', 'image/webp' => 'webp',
        'image/bmp'   => 'bmp',  'image/tiff' => 'tiff', 'image/x-bmp' => 'bmp',
        'application/pdf' => 'pdf', 'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt', 'text/csv' => 'csv', 'application/json' => 'json',
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
    if (strpos($mime, 'image/') === 0) return '🖼';
    if ($mime === 'application/pdf')   return '📄';
    if (strpos($mime, 'word') !== false) return '📝';
    if (strpos($mime, 'excel') !== false || strpos($mime, 'spreadsheet') !== false || $mime === 'text/csv') return '📊';
    if ($mime === 'text/plain')        return '📃';
    if ($mime === 'application/json')  return '🔧';
    return '📎';
}

function formatFileSize(int $bytes): string {
    if ($bytes < 1024)         return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

// ── Verify login password ─────────────────────────────────────────────────────
function checkPassword(string $input): bool {
    if (empty(APP_PASSWORD_HASH)) {
        // Hash not yet configured — deny all logins until make_hash.php is run
        return false;
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

    // Try file_get_contents
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

    // Fallback: cURL
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

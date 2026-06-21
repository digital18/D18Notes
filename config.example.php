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
function insertNote(string $note, string $ip, string $browser, string $location): void {
    $notes = loadNotes();
    $maxId = empty($notes) ? 0 : max(array_column($notes, 'Id'));
    $notes[] = [
        'Id'       => $maxId + 1,
        'Note'     => $note,
        'DateTime' => date('Y-m-d H:i:s'),
        'IP'       => $ip,
        'Browser'  => $browser,
        'Location' => $location,
    ];
    saveNotes($notes);
}

// ── Delete a note by Id ───────────────────────────────────────────────────────
function deleteNote(int $id): void {
    $notes = loadNotes();
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

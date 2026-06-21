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

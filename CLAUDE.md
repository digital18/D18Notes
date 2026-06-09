# CLAUDE.md — D18 Notes

> This file gives Claude Code full context about the D18 Notes project so it can assist effectively without re-reading the entire codebase each session.

---

## Project Overview

**D18 Notes** is a self-hosted personal notes web app built in pure PHP. It has no framework dependency and requires no database — all data is stored in a single AES-256-CBC encrypted JSON file (`notes.dat`) on the server.

Key design goals:
- Zero database setup — works on any PHP host
- Encrypted data at rest — `notes.dat` is unreadable without the key
- Real-time feel — JS polls every 4 seconds for new notes
- PWA installable — works as a home screen app on mobile and desktop
- Single-developer use — one password, one encrypted file, one person

---

## Architecture

```
Browser
  │
  ├── login.php          → session auth (bcrypt + CAPTCHA)
  │
  ├── index.php          → main notes UI (chat-style, live polling)
  │     ├── POST note    → insertNote() → saveNotes() → notes.dat
  │     ├── POST delete  → deleteNote() → saveNotes() → notes.dat
  │     └── GET          → fetchNotes() → decryptData() → render
  │
  ├── fetch_notes.php    → JSON endpoint for live polling (every 4s)
  │     └── returns notes with Id > last_id
  │
  ├── logout.php         → destroys session
  │
  └── config.php         → all logic: encryption, file I/O, helpers
```

### Data Flow

```
Write:  PHP array → json_encode → AES-256-CBC encrypt → base64 → notes.dat
Read:   notes.dat → base64 decode → AES-256-CBC decrypt → json_decode → PHP array
```

### Encryption Details

- **Algorithm:** AES-256-CBC (`openssl_encrypt`)
- **Key derivation:** `hash('sha256', ENCRYPT_SECRET, true)` → 32 raw bytes
- **IV:** `openssl_random_pseudo_bytes(16)` — new random IV on every write
- **Storage format:** `base64(IV) . ':' . base64(ciphertext)`
- **File:** `notes.dat` in project root

---

## File Structure

```
D18Notes/
├── config.php           ← Core logic: encryption, file I/O, helpers, auth
├── config.example.php   ← Template for new installs (safe to commit)
├── index.php            ← Main notes page (chat UI + all features)
├── login.php            ← Login page (bcrypt password + math CAPTCHA)
├── logout.php           ← Session destroy + redirect
├── fetch_notes.php      ← JSON polling endpoint (returns new notes)
├── make_hash.php        ← One-time bcrypt hash generator (delete after use)
├── icon.svg             ← App icon (SVG, scales to any size)
├── icon.php             ← PNG icon generator via GD (for apple-touch-icon)
├── manifest.json        ← PWA manifest
├── sw.js                ← Service worker (offline + cache)
├── offline.html         ← Offline fallback page
├── notes.dat            ← Encrypted data file (auto-created, gitignored)
├── .htaccess            ← Blocks direct access to sensitive files
├── .user.ini            ← PHP-FPM error display settings
├── CLAUDE.md            ← This file
├── README.md            ← Project documentation
└── LICENSE              ← MIT License
```

---

## Key Functions in `config.php`

| Function | Purpose |
|----------|---------|
| `getEncryptKey()` | Derives 32-byte AES key from `ENCRYPT_SECRET` |
| `encryptData(string)` | Encrypts JSON string → storable format |
| `decryptData(string)` | Decrypts stored format → JSON string |
| `loadNotes()` | Reads and decrypts `notes.dat` → PHP array |
| `saveNotes(array)` | Encrypts and writes PHP array → `notes.dat` |
| `fetchNotes()` | Returns all notes sorted oldest → newest |
| `insertNote(...)` | Adds a new note with auto-increment Id |
| `deleteNote(int)` | Removes note by Id and re-saves file |
| `checkPassword(string)` | Verifies login via `password_verify()` |
| `getClientIP()` | Extracts real IP (proxy-aware) |
| `getBrowser()` | Detects browser from User-Agent |
| `getLocation(string)` | Geo-locates IP via ip-api.com (with cURL fallback) |

---

## Notes Data Structure

Each note in the JSON array:

```json
{
  "Id": 1,
  "Note": "Note content here",
  "DateTime": "2024-01-15 10:32:00",
  "IP": "203.0.113.42",
  "Browser": "Chrome",
  "Location": "Mumbai, Maharashtra, India"
}
```

---

## Frontend Features (index.php)

- **Live polling:** `fetch_notes.php?last_id=X` every 4 seconds via `fetch()`
- **Search:** Client-side, filters `notesData` JS array, keyboard navigable
- **Copy tooltip:** Appears on text selection inside note bubbles
- **Info tooltip:** Hover/tap ℹ icon shows IP, browser, location
- **PWA install:** `beforeinstallprompt` event triggers "⬇ Install" button
- **Service worker:** Registered on both `index.php` and `login.php`

---

## Common Development Tasks

### Change the login password
1. Open `make_hash.php` in browser
2. Enter new password → copy the hash
3. Paste into `config.php` → `APP_PASSWORD_HASH`
4. Delete `make_hash.php` from server

### Change the encryption secret
Update `ENCRYPT_SECRET` in `config.php`. **Important:** Changing this after notes exist will make existing notes unreadable. Export notes first.

### Add a new note field
1. Add field to the `$notes[]` array in `insertNote()` in `config.php`
2. Add it to the PHP HTML template in `index.php` (info tooltip section)
3. Add it to `buildNoteHTML()` JS function in `index.php`
4. Add it to `fetch_notes.php` response (it's automatic since we return full note array)

### Update the PWA cache version
Change `CACHE_VER` in `sw.js` (e.g. `d18notes-v2`) to force all clients to refresh cache.

### Deploy to shared hosting
1. Upload all files except `notes.dat`, `make_hash.php`, `debug.php`
2. Copy `config.example.php` → `config.php`, fill in your values
3. Run `make_hash.php` once to generate password hash, then delete it
4. Ensure `notes.dat` directory is writable by PHP

---

## Coding Conventions

- **PHP:** Procedural, no OOP, no framework. Functions in `config.php`, presentation in page files.
- **CSS:** Custom properties (`--bg`, `--surface`, `--accent`, etc.) defined in `:root`
- **JS:** Vanilla ES6+, no libraries. All JS is inline at bottom of `index.php`
- **HTML:** Semantic, minimal. PHP renders initial state; JS handles live updates
- **Naming:** `camelCase` for PHP functions and JS variables, `kebab-case` for CSS classes

---

## Security Checklist

- [ ] `APP_PASSWORD_HASH` set to a real bcrypt hash (not placeholder)
- [ ] `ENCRYPT_SECRET` changed from the default value
- [ ] `make_hash.php` deleted from server after use
- [ ] `debug.php` deleted from server
- [ ] `bridge.php` deleted if not using bridge approach
- [ ] `.htaccess` uploaded to block direct file access
- [ ] `notes.dat` not committed to version control

---

## Environment Notes

- **Local dev:** Laravel Herd (`d18notes.test`) — PHP 8.x
- **Production:** Shared hosting (cPanel/Apache) — PHP 7.4+
- **PHP minimum:** 7.4 (uses typed properties, `array_filter` with arrow functions avoided for compat)
- **Required extensions:** `openssl`, `session` (both enabled by default on most hosts)
- **Optional extensions:** `gd` (for PNG icon generation), `curl` (for IP geolocation fallback)

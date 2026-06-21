<?php
require_once 'config.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// Delete note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) deleteNote($id);
    header('Location: index.php');
    exit;
}

// Edit note (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    ob_start();
    $id   = (int)($_POST['id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $dt   = trim($_POST['datetime'] ?? '');
    if ($id > 0 && $note !== '') {
        $dt = ($dt !== '') ? $dt : date('Y-m-d H:i:s');
        updateNote($id, $note, $dt);
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Star / unstar note (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'star') {
    ob_start();
    $id     = (int)($_POST['id'] ?? 0);
    $newCat = 'general';
    if ($id > 0) {
        $notes = loadNotes();
        foreach ($notes as &$n) {
            if ((int)$n['Id'] === $id) {
                $current       = $n['Category'] ?? 'general';
                $newCat        = ($current === 'star') ? 'general' : 'star';
                $n['Category'] = $newCat;
                break;
            }
        }
        unset($n);
        saveNotes($notes);
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'category' => $newCat]);
    exit;
}

// Add note (AJAX, supports text + file attachments)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    ob_start(); // buffer any stray PHP warnings so they don't corrupt JSON
    $note        = trim($_POST['note'] ?? '');
    $attachments = [];

    if (!empty($_FILES['files']['name'][0])) {
        $f     = $_FILES['files'];
        $count = min(count($f['name']), MAX_FILES);
        for ($i = 0; $i < $count; $i++) {
            if ((int)$f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            try {
                $attachments[] = uploadMedia([
                    'name'     => $f['name'][$i],
                    'type'     => $f['type'][$i],
                    'tmp_name' => $f['tmp_name'][$i],
                    'error'    => $f['error'][$i],
                    'size'     => $f['size'][$i],
                ]);
            } catch (RuntimeException $e) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
    }

    ob_end_clean();

    if ($note !== '' || !empty($attachments)) {
        $ip = getClientIP();
        insertNote($note, $ip, getBrowser(), getLocation($ip), $attachments);
        $allNotes = fetchNotes();
        $newNote  = end($allNotes);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'note' => $newNote]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Nothing to save.']);
    }
    exit;
}

$notes          = fetchNotes();
$count          = count($notes);
$bodyClass      = (BUBBLE_ALIGN === 'left') ? 'bubbles-left' : 'bubbles-right';
$defaultTheme   = APP_THEME;
$accentColor    = ACCENT_COLOR;
$bubbleBg       = BUBBLE_BG;
$bubbleText     = BUBBLE_TEXT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>D18 Notes</title>

<!-- PWA -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#6c63ff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="D18 Notes">
<meta name="application-name" content="D18 Notes">
<meta name="msapplication-TileColor" content="#6c63ff">

<!-- Icons -->
<link rel="icon" type="image/svg+xml" href="icon.svg">
<link rel="apple-touch-icon" href="icon.php?size=192">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    /* ── Light theme (default) ─────────────── */
    --bg:        #f0f2f7;
    --surface:   #ffffff;
    --border:    rgba(0,0,0,0.08);
    --text:      #1e1e2e;
    --muted:     #8890a4;
    --input-bg:  #f0f2f7;
    --shadow:    rgba(0,0,0,0.08);

    /* ── From config.php ───────────────────── */
    --accent:    <?= htmlspecialchars($accentColor, ENT_QUOTES) ?>;
    --note-bg:   <?= htmlspecialchars($bubbleBg,    ENT_QUOTES) ?>;
    --note-text: <?= htmlspecialchars($bubbleText,  ENT_QUOTES) ?>;

    /* ── Legacy alias (avatar, send btn etc.) ─ */
    --bubble:    var(--accent);

    --radius:    18px;
  }

  /* ── Dark theme ────────────────────────── */
  [data-theme="dark"] {
    --bg:       #0d0d1a;
    --surface:  #16162a;
    --border:   rgba(255,255,255,0.08);
    --text:     #e8eaf6;
    --muted:    #5c6680;
    --input-bg: #1e1e36;
    --shadow:   rgba(0,0,0,0.35);
  }

  html, body { height: 100%; }

  body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    flex-direction: column;
    height: 100dvh;
    overflow: hidden;
  }

  /* ── Header ─────────────────────────────── */
  header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    z-index: 20;
  }

  .header-left { display: flex; align-items: center; gap: 12px; }

  .avatar {
    width: 40px; height: 40px;
    background: var(--bubble);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  .header-info h2 { font-size: 1rem; font-weight: 700; color: var(--text); }
  .header-info small { font-size: 0.75rem; color: var(--muted); }

  .header-actions { display: flex; gap: 10px; align-items: center; }

  .note-count {
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 28%, transparent);
    color: var(--accent);
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
  }

  .btn-logout {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.25);
    color: #ef4444;
    font-size: 0.78rem;
    font-weight: 600;
    font-family: inherit;
    padding: 7px 14px;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
  }
  .btn-logout:hover { background: rgba(239,68,68,0.15); }

  #installBtn {
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 32%, transparent);
    color: var(--accent);
    font-size: 0.78rem;
    font-weight: 600;
    font-family: inherit;
    padding: 7px 14px;
    border-radius: 10px;
    cursor: pointer;
    display: none;
    align-items: center;
    gap: 5px;
    transition: background 0.2s;
  }
  #installBtn:hover { background: color-mix(in srgb, var(--accent) 20%, transparent); }

  /* ── Search bar ──────────────────────────── */
  .search-bar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 10px 20px;
    flex-shrink: 0;
    position: relative;
    z-index: 15;
  }

  .search-inner {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    background: var(--input-bg);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 0 14px;
    gap: 10px;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  }

  .search-inner:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent);
    background: var(--surface);
  }

  .search-icon { color: var(--muted); font-size: 1rem; line-height: 1; flex-shrink: 0; }

  #searchInput {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    padding: 11px 0;
    font-size: 0.9rem;
    font-family: inherit;
    color: var(--text);
  }
  #searchInput::placeholder { color: var(--muted); }

  #searchClear {
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 0.85rem;
    padding: 4px 6px;
    border-radius: 6px;
    line-height: 1;
    display: none;
    transition: background 0.15s, color 0.15s;
  }
  #searchClear:hover { background: rgba(0,0,0,0.06); color: var(--text); }

  /* search count badge */
  .search-count {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--accent);
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    border-radius: 20px;
    padding: 2px 8px;
    white-space: nowrap;
    display: none;
  }

  /* dropdown results */
  .search-results {
    position: absolute;
    top: calc(100% - 2px);
    left: 20px;
    right: 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 12px 36px rgba(0,0,0,0.12);
    max-height: 380px;
    overflow-y: auto;
    display: none;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.1) transparent;
  }

  .search-results::-webkit-scrollbar       { width: 4px; }
  .search-results::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }

  .search-result-item {
    padding: 13px 18px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    cursor: pointer;
    transition: background 0.12s;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }
  .search-result-item:hover  { background: color-mix(in srgb, var(--accent) 8%, transparent); }
  .search-result-item:last-child { border-bottom: none; }
  .search-result-item.focused { background: color-mix(in srgb, var(--accent) 8%, transparent); }

  .result-num {
    width: 22px; height: 22px;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    color: var(--accent);
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
  }

  .result-body { flex: 1; min-width: 0; }

  .result-preview {
    font-size: 0.875rem;
    color: var(--text);
    line-height: 1.45;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .result-preview mark {
    background: color-mix(in srgb, var(--accent) 18%, transparent);
    color: var(--accent);
    border-radius: 3px;
    padding: 0 2px;
    font-style: normal;
  }

  .result-meta {
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 4px;
  }

  .search-no-result {
    padding: 22px;
    text-align: center;
    color: var(--muted);
    font-size: 0.875rem;
  }
  .search-no-result span { font-size: 1.5rem; display: block; margin-bottom: 6px; }

  /* ── Chat area ───────────────────────────── */
  .chat-area {
    flex: 1;
    overflow-y: auto;
    padding: 24px 16px 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.12) transparent;
  }
  .chat-area::-webkit-scrollbar       { width: 5px; }
  .chat-area::-webkit-scrollbar-track { background: transparent; }
  .chat-area::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 3px; }

  .date-sep {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--muted);
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
  }
  .date-sep::before, .date-sep::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(0,0,0,0.1);
  }

  .note-wrapper {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    max-width: 80%;
    align-self: flex-end;
    scroll-margin-top: 20px;
  }

  /* Left-aligned bubbles */
  body.bubbles-left .note-wrapper {
    align-items: flex-start;
    align-self: flex-start;
  }

  .note-bubble {
    background: var(--note-bg);
    color: var(--note-text);
    padding: 14px 18px;
    border-radius: var(--radius) var(--radius) 4px var(--radius);
    font-size: 0.95rem;
    line-height: 1.6;
    box-shadow: 0 2px 12px var(--shadow);
    transition: box-shadow 0.3s;
    border: 1px solid var(--border);
  }

  /* Text body inside bubble — owns pre-wrap so images/cards are not affected */
  .note-text-body {
    white-space: pre-wrap;
    word-break: break-word;
  }

  /* Left tail on left-aligned bubbles */
  body.bubbles-left .note-bubble {
    border-radius: var(--radius) var(--radius) var(--radius) 4px;
  }

  /* highlight animation when jumped to */
  .note-wrapper.highlight .note-bubble {
    animation: pop-highlight 1.8s ease forwards;
  }
  @keyframes pop-highlight {
    0%   { box-shadow: 0 0 0 0    color-mix(in srgb, var(--accent) 80%, transparent); transform: scale(1); }
    20%  { box-shadow: 0 0 0 10px color-mix(in srgb, var(--accent) 20%, transparent); transform: scale(1.02); }
    100% { box-shadow: 0 2px 12px var(--shadow); transform: scale(1); }
  }

  .note-time {
    font-size: 0.68rem;
    color: var(--muted);
    margin-top: 5px;
    padding-right: 2px;
  }

  /* ── Note action row (time + icons) ─────── */
  .note-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 5px;
    padding: 0 2px;
    gap: 6px;
  }

  .action-icons {
    display: flex;
    align-items: center;
    gap: 1px;
  }

  /* Base icon button */
  .icon-btn {
    background: none;
    border: none;
    cursor: pointer;
    width: 26px;
    height: 26px;
    border-radius: 7px;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.15s, background 0.15s;
    font-family: inherit;
    padding: 0;
    line-height: 1;
  }

  /* Show icons on note hover */
  .note-wrapper:hover .icon-btn { opacity: 0.5; }
  .icon-btn:hover                { opacity: 1 !important; background: rgba(0,0,0,0.07); }

  /* Always visible on touch devices */
  @media (hover: none) {
    .icon-btn { opacity: 0.45; }
  }

  /* Info icon */
  .info-btn { color: var(--accent); }
  .info-btn:hover { background: color-mix(in srgb, var(--accent) 12%, transparent) !important; }

  /* Delete icon */
  .del-btn { color: #ef4444; }
  .del-btn:hover { background: rgba(239,68,68,0.1) !important; opacity: 1 !important; }

  /* Edit icon */
  .edit-btn { color: #f59e0b; }
  .edit-btn:hover { background: rgba(245,158,11,0.1) !important; opacity: 1 !important; }

  /* Info tooltip */
  .info-wrap { position: relative; }

  .info-tooltip {
    position: absolute;
    bottom: calc(100% + 8px);
    right: -4px;
    background: #1e1e2e;
    color: #fff;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 0.72rem;
    font-family: 'Inter', sans-serif;
    white-space: nowrap;
    box-shadow: 0 8px 28px rgba(0,0,0,0.22);
    opacity: 0;
    pointer-events: none;
    transform: translateY(6px) scale(0.95);
    transform-origin: bottom right;
    transition: opacity 0.15s, transform 0.15s;
    z-index: 100;
    min-width: 185px;
  }

  /* Arrow */
  .info-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    right: 10px;
    border: 5px solid transparent;
    border-top-color: #1e1e2e;
  }

  /* Left-side tooltip positioning */
  body.bubbles-left .info-tooltip {
    right: auto;
    left: -4px;
    transform-origin: bottom left;
  }
  body.bubbles-left .info-tooltip::after {
    right: auto;
    left: 10px;
  }

  /* Show on hover (desktop) or .show class (mobile tap) */
  .info-wrap:hover .info-tooltip,
  .info-wrap.show  .info-tooltip {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0) scale(1);
  }

  .info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    color: rgba(255,255,255,0.75);
    line-height: 1.35;
  }

  .info-row + .info-row { border-top: 1px solid rgba(255,255,255,0.08); }
  .info-row .i-ico      { font-size: 0.72rem; flex-shrink: 0; opacity: 0.9; }
  .info-row .i-val      { word-break: break-all; }

  /* empty state */
  .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    text-align: center;
    padding: 40px 20px;
  }
  .empty-state .empty-icon { font-size: 4rem; margin-bottom: 16px; opacity: 0.4; }
  .empty-state h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 6px; color: var(--text); }

  /* ── Input area ──────────────────────────── */
  .input-area {
    background: var(--surface);
    border-top: 1px solid var(--border);
    padding: 16px;
    flex-shrink: 0;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
  }

  .input-inner {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    gap: 10px;
    align-items: flex-end;
  }

  .error-banner {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.25);
    color: #ef4444;
    font-size: 0.8rem;
    padding: 8px 12px;
    border-radius: 10px;
    margin-bottom: 10px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
  }

  textarea {
    flex: 1;
    background: var(--input-bg);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    padding: 13px 16px;
    font-size: 0.95rem;
    font-family: inherit;
    color: var(--text);
    resize: none;
    outline: none;
    min-height: 52px;
    max-height: 180px;
    line-height: 1.5;
    transition: border-color 0.2s, background 0.2s;
  }
  textarea:focus {
    border-color: var(--accent);
    background: var(--surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent);
  }
  textarea::placeholder { color: var(--muted); }

  button.send-btn {
    background: var(--accent);
    border: none;
    border-radius: 14px;
    width: 52px;
    height: 52px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.3rem;
    color: #fff;
    transition: opacity 0.2s, transform 0.1s;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--accent) 40%, transparent);
  }
  button.send-btn:hover  { opacity: 0.88; }
  button.send-btn:active { transform: scale(0.93); }

  /* ── Footer ──────────────────────────────── */
  .app-footer {
    text-align: center;
    padding: 6px 0 2px;
    font-size: 0.68rem;
    color: var(--muted);
    letter-spacing: 0.2px;
    user-select: none;
  }
  .app-footer a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
    transition: opacity 0.15s;
  }
  .app-footer a:hover { opacity: 0.75; }

  #bottom { height: 1px; }

  /* ── Copy tooltip ───────────────────────── */
  #copyTooltip {
    position: fixed;
    transform: translateX(-50%);
    background: #1e1e2e;
    color: #fff;
    border-radius: 20px;
    padding: 7px 16px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    z-index: 999;
    white-space: nowrap;
    box-shadow: 0 6px 24px rgba(0,0,0,0.3);
    display: none;
    opacity: 0;
    transition: opacity 0.15s, background 0.2s;
    user-select: none;
    pointer-events: auto;
  }
  #copyTooltip.visible { opacity: 1; }
  #copyTooltip.copied  { background: #16a34a; }

  /* small arrow pointing down */
  #copyTooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #1e1e2e;
    transition: border-top-color 0.2s;
  }
  #copyTooltip.copied::after { border-top-color: #16a34a; }

  /* ── Live indicator ──────────────────────── */
  .live-dot {
    display: inline-block;
    width: 7px; height: 7px;
    background: #22c55e;
    border-radius: 50%;
    margin-right: 5px;
    vertical-align: middle;
    box-shadow: 0 0 0 0 rgba(34,197,94,0.5);
    animation: live-pulse 2s ease-in-out infinite;
  }
  @keyframes live-pulse {
    0%   { box-shadow: 0 0 0 0   rgba(34,197,94,0.5); }
    60%  { box-shadow: 0 0 0 5px rgba(34,197,94,0);   }
    100% { box-shadow: 0 0 0 0   rgba(34,197,94,0);   }
  }

  /* ── New notes floating button ───────────── */
  #newNoteBtn {
    position: fixed;
    bottom: 90px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 10px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    box-shadow: 0 6px 24px color-mix(in srgb, var(--accent) 45%, transparent);
    display: none;
    opacity: 0;
    transition: opacity 0.3s, transform 0.3s;
    z-index: 50;
    white-space: nowrap;
  }
  #newNoteBtn.visible {
    display: block;
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }

  /* ── Responsive ──────────────────────────── */
  @media (max-width: 600px) {
    .note-wrapper { max-width: 95%; }
    .note-count   { display: none; }
    header        { padding: 12px 14px; }
    .search-bar   { padding: 8px 12px; }
    .search-results { left: 12px; right: 12px; }
    .filter-bar   { padding: 0 12px; }
    .filter-tab   { padding: 9px 12px; }
    .chat-area    { padding: 16px 10px 10px; }
    .input-area   { padding: 10px; }
  }

  @media (min-width: 900px) {
    .chat-area  { padding: 28px 12%; }
    .input-area { padding: 16px 12%; }
    .search-bar { padding: 10px 12%; }
    .search-results { left: 12%; right: 12%; }
    .filter-bar { padding: 0 12%; }
  }

  /* ── Edit Modal ──────────────────────────── */
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 200;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(3px);
  }

  .modal-card {
    background: var(--surface);
    border-radius: 20px;
    padding: 28px 28px 24px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.22);
    display: flex;
    flex-direction: column;
    gap: 14px;
    animation: modal-in 0.2s ease;
  }

  @keyframes modal-in {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1)    translateY(0);    }
  }

  .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .modal-header h3 {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text);
  }

  .modal-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    color: var(--muted);
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    font-family: inherit;
  }
  .modal-close:hover { background: rgba(0,0,0,0.07); color: var(--text); }

  .edit-textarea {
    width: 100%;
    background: var(--bg);
    border: 1.5px solid rgba(0,0,0,0.1);
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 0.95rem;
    font-family: inherit;
    color: var(--text);
    resize: vertical;
    min-height: 110px;
    max-height: 260px;
    outline: none;
    line-height: 1.55;
    transition: border-color 0.2s, background 0.2s;
  }
  .edit-textarea:focus {
    border-color: var(--accent);
    background: var(--surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent);
  }

  .edit-dt-label {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: -6px;
  }

  .edit-dt-input {
    width: 100%;
    background: var(--input-bg);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 0.9rem;
    font-family: inherit;
    color: var(--text);
    outline: none;
    transition: border-color 0.2s, background 0.2s;
    color-scheme: light dark;
  }
  .edit-dt-input:focus {
    border-color: var(--accent);
    background: var(--surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent);
  }

  .modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 4px;
  }

  .btn-modal-cancel {
    background: rgba(0,0,0,0.06);
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-size: 0.88rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    color: var(--muted);
    transition: background 0.15s;
  }
  .btn-modal-cancel:hover { background: rgba(0,0,0,0.1); }

  .btn-modal-save {
    background: var(--accent);
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-size: 0.88rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    color: #fff;
    transition: opacity 0.2s;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--accent) 35%, transparent);
  }
  .btn-modal-save:hover   { opacity: 0.88; }
  .btn-modal-save:active  { transform: scale(0.97); }
  .btn-modal-save:disabled { opacity: 0.6; cursor: not-allowed; }

  /* ── Theme toggle button ─────────────────── */
  .btn-theme {
    background: rgba(0,0,0,0.06);
    border: 1px solid var(--border);
    border-radius: 10px;
    width: 36px;
    height: 36px;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    flex-shrink: 0;
  }
  .btn-theme:hover { background: rgba(0,0,0,0.1); }
  [data-theme="dark"] .btn-theme {
    background: rgba(255,255,255,0.06);
  }
  [data-theme="dark"] .btn-theme:hover { background: rgba(255,255,255,0.12); }

  /* ── Dark mode misc overrides ────────────── */
  [data-theme="dark"] .modal-card { background: var(--surface); }
  [data-theme="dark"] .edit-textarea { background: var(--input-bg); }
  [data-theme="dark"] header { box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
  [data-theme="dark"] .input-area { box-shadow: 0 -2px 10px rgba(0,0,0,0.3); }
  [data-theme="dark"] .btn-modal-cancel { background: rgba(255,255,255,0.08); color: var(--muted); }
  [data-theme="dark"] .btn-modal-cancel:hover { background: rgba(255,255,255,0.14); }
  [data-theme="dark"] #searchClear:hover { background: rgba(255,255,255,0.08); }

  /* ── Filter tabs ─────────────────────────── */
  .filter-bar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 20px;
    display: flex;
    align-items: center;
    flex-shrink: 0;
    z-index: 14;
  }

  .filter-tab {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    padding: 9px 16px;
    font-size: 0.82rem;
    font-weight: 600;
    font-family: inherit;
    color: var(--muted);
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
    white-space: nowrap;
  }
  .filter-tab:hover  { color: var(--text); }
  .filter-tab.active { color: var(--accent); border-bottom-color: var(--accent); }

  /* ── Star button ─────────────────────────── */
  .star-btn { color: var(--muted); }
  .star-btn:hover { background: rgba(245,158,11,0.1) !important; color: #f59e0b !important; opacity: 1 !important; }
  .star-btn.starred { color: #f59e0b; opacity: 1 !important; }

  /* ── Filter empty state ──────────────────── */
  .filter-empty {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    text-align: center;
    padding: 48px 20px;
  }
  .filter-empty .fe-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.35; }
  .filter-empty p { font-size: 0.9rem; }

  /* ── Attach button ───────────────────────── */
  .attach-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--muted);
    font-size: 1.3rem;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: color 0.15s, background 0.15s;
  }
  .attach-btn:hover { color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); }

  /* ── Attachment preview strip ────────────── */
  .attach-strip {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 10px;
  }

  .attach-preview-img {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1.5px solid var(--border);
    background: var(--input-bg);
  }
  .attach-preview-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
  }

  .attach-preview-file {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--input-bg);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: 0.78rem;
    color: var(--text);
    max-width: 200px;
    position: relative;
  }
  .attach-preview-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
  }

  .attach-remove {
    position: absolute;
    top: 3px; right: 3px;
    width: 18px; height: 18px;
    background: rgba(0,0,0,0.55);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 0.6rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    z-index: 1;
    padding: 0;
    font-family: inherit;
  }
  .attach-remove:hover { background: #ef4444; }

  /* ── Drag-over state ─────────────────────── */
  .input-area.drag-over {
    outline: 2px dashed var(--accent);
    outline-offset: -6px;
    background: color-mix(in srgb, var(--accent) 6%, var(--surface));
  }

  /* ── Note images ─────────────────────────── */
  .note-img-wrap { display: block; margin-bottom: 6px; }
  .note-img-wrap:last-child { margin-bottom: 0; }

  .note-img {
    display: block;
    max-width: 100%;
    max-height: 300px;
    border-radius: 10px;
    cursor: zoom-in;
    object-fit: contain;
    transition: opacity 0.2s;
  }
  .note-img:hover { opacity: 0.9; }

  /* ── Note file card ──────────────────────── */
  .note-file-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: color-mix(in srgb, var(--accent) 7%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 22%, transparent);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    margin-bottom: 6px;
    transition: background 0.15s;
  }
  .note-file-card:hover { background: color-mix(in srgb, var(--accent) 14%, transparent); }
  .note-file-card:last-child { margin-bottom: 0; }
  .file-card-icon { font-size: 1.5rem; flex-shrink: 0; line-height: 1; }
  .file-card-info { flex: 1; min-width: 0; }
  .file-card-name {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text);
  }
  .file-card-size {
    display: block;
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 2px;
  }
  .file-card-dl { color: var(--accent); font-size: 1rem; flex-shrink: 0; }

  /* Caption text below an attachment gets a small top gap */
  .note-img-wrap  + .note-text-body,
  .note-file-card + .note-text-body { margin-top: 8px; }

  /* ── Lightbox ────────────────────────────── */
  #lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.92);
    z-index: 600;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
    padding: 20px;
    backdrop-filter: blur(6px);
  }
  #lightbox.open { display: flex; }

  #lightboxImg {
    max-width: 100%;
    max-height: 88vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 24px 80px rgba(0,0,0,0.6);
    user-select: none;
    pointer-events: none;
  }

  #lightboxCaption {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    color: rgba(255,255,255,0.65);
    font-size: 0.78rem;
    white-space: nowrap;
    text-align: center;
    pointer-events: none;
  }

  #lightboxClose {
    position: fixed;
    top: 14px; right: 14px;
    background: rgba(255,255,255,0.12);
    border: none;
    border-radius: 50%;
    width: 38px; height: 38px;
    color: #fff;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    z-index: 601;
    font-family: inherit;
  }
  #lightboxClose:hover { background: rgba(255,255,255,0.22); }

  /* ── Upload error banner ─────────────────── */
  .upload-error {
    max-width: 900px;
    margin: 0 auto 8px;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.28);
    color: #ef4444;
    font-size: 0.8rem;
    padding: 8px 12px;
    border-radius: 10px;
    display: none;
  }
</style>
</head>
<body class="<?= $bodyClass ?>">

<!-- Header -->
<header>
  <div class="header-left">
    <div class="avatar">📝</div>
    <div class="header-info">
      <h2>My Notes</h2>
      <small><span class="live-dot"></span>Live</small>
    </div>
  </div>
  <div class="header-actions">
    <span class="note-count"><?= $count ?> note<?= $count !== 1 ? 's' : '' ?></span>
    <button id="themeToggle" class="btn-theme" title="Toggle dark / light theme">🌙</button>
    <button id="installBtn" title="Install as App">⬇ Install</button>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</header>

<!-- Search bar -->
<div class="search-bar">
  <div class="search-inner">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Search notes…" autocomplete="off" spellcheck="false">
    <span class="search-count" id="searchCount"></span>
    <button id="searchClear" title="Clear search">✕</button>
  </div>
  <div class="search-results" id="searchResults"></div>
</div>

<!-- Filter tabs -->
<div class="filter-bar">
  <button class="filter-tab active" data-filter="all">All</button>
  <button class="filter-tab" data-filter="general">General</button>
  <button class="filter-tab" data-filter="star">⭐ Starred</button>
</div>

<!-- Chat window -->
<div class="chat-area" id="chatArea">

  <?php if (empty($notes)): ?>
    <div class="empty-state">
      <div class="empty-icon">🗒️</div>
      <h3>No notes yet</h3>
      <p>Type your first note below and hit send.</p>
    </div>
  <?php else: ?>

    <?php
    $prevDate = null;
    foreach ($notes as $note):
      $dt        = new DateTime($note['DateTime']);
      $dateKey   = $dt->format('Y-m-d');
      $today     = (new DateTime())->format('Y-m-d');
      $yesterday = (new DateTime('yesterday'))->format('Y-m-d');

      if ($dateKey !== $prevDate):
        $prevDate = $dateKey;
        if ($dateKey === $today)         $label = 'Today';
        elseif ($dateKey === $yesterday) $label = 'Yesterday';
        else                             $label = $dt->format('F j, Y');
    ?>
      <div class="date-sep"><?= htmlspecialchars($label) ?></div>
    <?php endif; ?>

    <?php
      $noteCat  = $note['Category'] ?? 'general';
      $noteAtts = $note['Attachments'] ?? [];
    ?>
    <div class="note-wrapper" id="note-<?= (int)$note['Id'] ?>" data-category="<?= htmlspecialchars($noteCat, ENT_QUOTES) ?>">
      <div class="note-bubble"><?php
        foreach ($noteAtts as $att):
          if (strpos($att['mime'], 'image/') === 0): ?>
<a class="note-img-wrap" href="media/<?= htmlspecialchars($att['filename'], ENT_QUOTES) ?>" target="_blank"
 onclick="event.preventDefault();openLightbox('media/<?= htmlspecialchars($att['filename'], ENT_QUOTES) ?>','<?= htmlspecialchars($att['original'], ENT_QUOTES) ?>')"
><img class="note-img" src="media/<?= htmlspecialchars($att['filename'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($att['original']) ?>" loading="lazy"></a><?php
          else: ?>
<a class="note-file-card" href="media/<?= htmlspecialchars($att['filename'], ENT_QUOTES) ?>" download="<?= htmlspecialchars($att['original'], ENT_QUOTES) ?>" target="_blank"><span class="file-card-icon"><?= fileIconEmoji($att['mime']) ?></span><div class="file-card-info"><span class="file-card-name"><?= htmlspecialchars($att['original']) ?></span><span class="file-card-size"><?= formatFileSize((int)$att['size']) ?></span></div><span class="file-card-dl">⬇</span></a><?php
          endif;
        endforeach;
        if ($note['Note'] !== ''): ?><div class="note-text-body"><?= nl2br(htmlspecialchars($note['Note'])) ?></div><?php
        endif; ?></div>

      <div class="note-actions">
        <span class="note-time"><?= $dt->format('h:i A') ?></span>
        <div class="action-icons">

          <?php if (!empty($note['IP']) || !empty($note['Browser']) || !empty($note['Location'])): ?>
          <div class="info-wrap">
            <button type="button" class="icon-btn info-btn" title="Info">ℹ</button>
            <div class="info-tooltip">
              <?php if (!empty($note['IP'])): ?>
                <div class="info-row"><span class="i-ico">🌐</span><span class="i-val"><?= htmlspecialchars($note['IP']) ?></span></div>
              <?php endif; ?>
              <?php if (!empty($note['Browser'])): ?>
                <div class="info-row"><span class="i-ico">🖥</span><span class="i-val"><?= htmlspecialchars($note['Browser']) ?></span></div>
              <?php endif; ?>
              <?php if (!empty($note['Location'])): ?>
                <div class="info-row"><span class="i-ico">📍</span><span class="i-val"><?= htmlspecialchars($note['Location']) ?></span></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <button type="button" class="icon-btn star-btn<?= ($noteCat === 'star') ? ' starred' : '' ?>"
            data-id="<?= (int)$note['Id'] ?>"
            title="<?= ($noteCat === 'star') ? 'Unstar note' : 'Star note' ?>">
            <?= ($noteCat === 'star') ? '⭐' : '☆' ?>
          </button>

          <button type="button" class="icon-btn edit-btn" title="Edit note"
            data-id="<?= (int)$note['Id'] ?>"
            data-note="<?= htmlspecialchars($note['Note'], ENT_QUOTES) ?>"
            data-dt="<?= htmlspecialchars($note['DateTime'], ENT_QUOTES) ?>">✏️</button>

          <form method="POST" action="index.php" onsubmit="return confirm('Delete this note?')" style="display:contents">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$note['Id'] ?>">
            <button type="submit" class="icon-btn del-btn" title="Delete note">🗑</button>
          </form>

        </div>
      </div>
    </div>

    <?php endforeach; ?>

  <?php endif; ?>

  <div id="filterEmpty" class="filter-empty">
    <div class="fe-icon">⭐</div>
    <p id="filterEmptyMsg">No starred notes yet.</p>
  </div>

  <div id="bottom"></div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay" style="display:none" onclick="handleModalOverlayClick(event)">
  <div class="modal-card">
    <div class="modal-header">
      <h3>✏️ Edit Note</h3>
      <button type="button" class="modal-close" onclick="closeEditModal()">✕</button>
    </div>
    <textarea id="editText" class="edit-textarea" placeholder="Note content…"></textarea>
    <label class="edit-dt-label">Date &amp; Time</label>
    <input type="datetime-local" id="editDatetime" class="edit-dt-input">
    <div class="modal-actions">
      <button type="button" class="btn-modal-cancel" onclick="closeEditModal()">Cancel</button>
      <button type="button" class="btn-modal-save" id="saveEditBtn" onclick="saveEdit()">Save Changes</button>
    </div>
  </div>
</div>

<!-- Floating "new notes" button -->
<button id="newNoteBtn" onclick="scrollToBottom()">⬇ New notes</button>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()">
  <button id="lightboxClose" onclick="event.stopPropagation(); closeLightbox()">✕</button>
  <img id="lightboxImg" src="" alt="">
  <div id="lightboxCaption"></div>
</div>

<!-- Input area -->
<div class="input-area" id="inputArea">
  <div class="attach-strip" id="attachStrip" style="display:none"></div>
  <div class="upload-error" id="uploadError"></div>

  <form method="POST" action="index.php" class="input-inner" id="noteForm" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add">
    <input type="file" id="fileInput" name="files[]" multiple
      accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.json"
      style="display:none">
    <button type="button" class="attach-btn" id="attachBtn" title="Attach file (📎)">📎</button>
    <textarea
      id="noteInput"
      name="note"
      placeholder="Write a note, paste an image, or drop a file…"
      rows="1"
      autofocus
    ></textarea>
    <button type="submit" class="send-btn" title="Send note">&#9658;</button>
  </form>
  <p class="app-footer">Made with ❤️ by <a href="https://digital18.in" target="_blank" rel="noopener">DIGITAL18.IN</a></p>
</div>

<script>
  // ── Theme (dark / light) ────────────────────────────────────────────────────
  const DEFAULT_THEME   = <?= json_encode($defaultTheme) ?>;
  const themeToggleBtn  = document.getElementById('themeToggle');

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    themeToggleBtn.textContent = theme === 'dark' ? '☀️' : '🌙';
    themeToggleBtn.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
  }

  // Apply saved preference or config default on first load
  const savedTheme = localStorage.getItem('d18notes-theme') || DEFAULT_THEME;
  applyTheme(savedTheme);

  themeToggleBtn.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
    const next    = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem('d18notes-theme', next);
  });

  // ── Scroll to bottom on load ────────────────
  const chat = document.getElementById('chatArea');
  chat.scrollTop = chat.scrollHeight;

  // ── Auto-grow textarea ──────────────────────
  const ta = document.getElementById('noteInput');
  ta.addEventListener('input', () => {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 180) + 'px';
  });
  ta.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      submitNote();
    }
  });

  // ── Notes index for search ──────────────────
  const notesData = [
    <?php foreach ($notes as $n): ?>
    {
      id:          <?= (int)$n['Id'] ?>,
      text:        <?= json_encode($n['Note']) ?>,
      datetime:    <?= json_encode($n['DateTime']) ?>,
      category:    <?= json_encode($n['Category'] ?? 'general') ?>,
      attachments: <?= json_encode($n['Attachments'] ?? []) ?>
    },
    <?php endforeach; ?>
  ];

  const searchInput   = document.getElementById('searchInput');
  const searchResults = document.getElementById('searchResults');
  const searchClear   = document.getElementById('searchClear');
  const searchCount   = document.getElementById('searchCount');

  let activeIndex = -1;

  function escapeRegex(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function formatDate(str) {
    const d = new Date(str.replace(' ', 'T'));
    return d.toLocaleString('en-IN', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit', hour12: true
    });
  }

  function truncate(text, max = 100) {
    return text.length > max ? text.slice(0, max) + '…' : text;
  }

  function renderResults(matches, query) {
    activeIndex = -1;
    if (!query) {
      searchResults.style.display = 'none';
      searchCount.style.display = 'none';
      return;
    }

    searchCount.textContent = matches.length + ' found';
    searchCount.style.display = matches.length ? 'inline-block' : 'none';

    if (matches.length === 0) {
      searchResults.innerHTML =
        '<div class="search-no-result"><span>🔍</span>No notes match "<strong>' +
        escapeHtml(query) + '</strong>"</div>';
      searchResults.style.display = 'block';
      return;
    }

    const re = new RegExp('(' + escapeRegex(query) + ')', 'gi');

    searchResults.innerHTML = matches.map((n, i) => {
      const preview   = truncate(n.text);
      const highlight = escapeHtml(preview).replace(
        new RegExp(escapeRegex(escapeHtml(query)), 'gi'),
        '<mark>$&</mark>'
      );
      return `<div class="search-result-item" data-id="${n.id}" data-index="${i}" onclick="jumpToNote(${n.id})">
        <div class="result-num">${i + 1}</div>
        <div class="result-body">
          <div class="result-preview">${highlight}</div>
          <div class="result-meta">📅 ${formatDate(n.datetime)}</div>
        </div>
      </div>`;
    }).join('');

    searchResults.style.display = 'block';
  }

  function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim();
    searchClear.style.display = q ? 'inline-block' : 'none';
    if (!q) { renderResults([], ''); return; }
    const matches = notesData.filter(n => n.text.toLowerCase().includes(q.toLowerCase()));
    renderResults(matches, q);
  });

  // Keyboard navigation inside dropdown
  searchInput.addEventListener('keydown', (e) => {
    const items = searchResults.querySelectorAll('.search-result-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % items.length;
      updateActive(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + items.length) % items.length;
      updateActive(items);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      items[activeIndex].click();
    } else if (e.key === 'Escape') {
      closeSearch();
    }
  });

  function updateActive(items) {
    items.forEach((el, i) => el.classList.toggle('focused', i === activeIndex));
    if (activeIndex >= 0) items[activeIndex].scrollIntoView({ block: 'nearest' });
  }

  searchClear.addEventListener('click', closeSearch);

  function closeSearch() {
    searchInput.value = '';
    searchClear.style.display = 'none';
    searchCount.style.display = 'none';
    searchResults.style.display = 'none';
    activeIndex = -1;
    searchInput.focus();
  }

  function jumpToNote(id) {
    searchResults.style.display = 'none';

    const el = document.getElementById('note-' + id);
    if (!el) return;

    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.remove('highlight');
    void el.offsetWidth; // reflow to restart animation
    el.classList.add('highlight');
    setTimeout(() => el.classList.remove('highlight'), 2000);
  }

  // Close dropdown on outside click
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-bar')) {
      searchResults.style.display = 'none';
    }
  });

  // ── Auto-poll for new notes ─────────────────────────────────────────────────
  let lastNoteId   = <?= $count > 0 ? (int)end($notes)['Id'] : 0 ?>;
  let noteCount    = <?= $count ?>;
  const newNoteBtn = document.getElementById('newNoteBtn');

  function isAtBottom() {
    return chat.scrollHeight - chat.scrollTop - chat.clientHeight < 60;
  }

  function scrollToBottom() {
    chat.scrollTo({ top: chat.scrollHeight, behavior: 'smooth' });
    newNoteBtn.classList.remove('visible');
    setTimeout(() => { newNoteBtn.style.display = 'none'; }, 300);
  }

  function dateLabel(dateStr) {
    const d     = new Date(dateStr.replace(' ', 'T'));
    const today = new Date(); today.setHours(0,0,0,0);
    const yest  = new Date(today); yest.setDate(yest.getDate() - 1);
    const nd    = new Date(d); nd.setHours(0,0,0,0);
    if (nd.getTime() === today.getTime()) return 'Today';
    if (nd.getTime() === yest.getTime())  return 'Yesterday';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  function noteDate(dateStr) {
    return dateStr.slice(0, 10); // YYYY-MM-DD
  }

  function formatTime(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
  }

  // ── Attachment helpers ──────────────────────────────────────────────────────
  function jsFileIcon(mime) {
    if (mime.startsWith('image/'))           return '🖼';
    if (mime === 'application/pdf')          return '📄';
    if (mime.includes('word'))               return '📝';
    if (mime.includes('excel') || mime.includes('spreadsheet') || mime === 'text/csv') return '📊';
    if (mime === 'text/plain')               return '📃';
    if (mime === 'application/json')         return '🔧';
    return '📎';
  }

  function jsFormatSize(bytes) {
    if (bytes < 1024)             return bytes + ' B';
    if (bytes < 1024 * 1024)     return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function buildAttachmentsHTML(attachments) {
    if (!attachments || !attachments.length) return '';
    return attachments.map(att => {
      if (att.mime.startsWith('image/')) {
        return `<a class="note-img-wrap" href="media/${escapeAttr(att.filename)}" target="_blank"
          onclick="event.preventDefault(); openLightbox('media/${escapeAttr(att.filename)}', '${escapeAttr(att.original)}')">
          <img class="note-img" src="media/${escapeAttr(att.filename)}" alt="${escapeAttr(att.original)}" loading="lazy">
        </a>`;
      }
      return `<a class="note-file-card" href="media/${escapeAttr(att.filename)}"
        download="${escapeAttr(att.original)}" target="_blank">
        <span class="file-card-icon">${jsFileIcon(att.mime)}</span>
        <div class="file-card-info">
          <span class="file-card-name">${escapeHtml(att.original)}</span>
          <span class="file-card-size">${jsFormatSize(att.size)}</span>
        </div>
        <span class="file-card-dl">⬇</span>
      </a>`;
    }).join('');
  }

  function buildNoteHTML(n) {
    const cat         = n.Category || 'general';
    const isStarred   = cat === 'star';
    const attachHTML  = buildAttachmentsHTML(n.Attachments || []);
    const noteText    = n.Note ? escapeHtml(n.Note).replace(/\n/g, '<br>') : '';
    const bubbleInner = attachHTML + (noteText ? `<div class="note-text-body">${noteText}</div>` : '');

    const infoRows = [
      n.IP       ? `<div class="info-row"><span class="i-ico">🌐</span><span class="i-val">${escapeHtml(n.IP)}</span></div>`       : '',
      n.Browser  ? `<div class="info-row"><span class="i-ico">🖥</span><span class="i-val">${escapeHtml(n.Browser)}</span></div>`   : '',
      n.Location ? `<div class="info-row"><span class="i-ico">📍</span><span class="i-val">${escapeHtml(n.Location)}</span></div>` : '',
    ].filter(Boolean).join('');

    const infoBtn = infoRows ? `
      <div class="info-wrap">
        <button type="button" class="icon-btn info-btn" title="Info">ℹ</button>
        <div class="info-tooltip">${infoRows}</div>
      </div>` : '';

    const starBtn = `<button type="button" class="icon-btn star-btn${isStarred ? ' starred' : ''}"
      data-id="${n.Id}" title="${isStarred ? 'Unstar note' : 'Star note'}">${isStarred ? '⭐' : '☆'}</button>`;

    const editBtn = `<button type="button" class="icon-btn edit-btn" title="Edit note"
      data-id="${n.Id}"
      data-note="${escapeAttr(n.Note)}"
      data-dt="${escapeAttr(n.DateTime)}">✏️</button>`;

    return `
      <div class="note-wrapper" id="note-${n.Id}" data-category="${escapeAttr(cat)}">
        <div class="note-bubble">${bubbleInner}</div>
        <div class="note-actions">
          <span class="note-time">${formatTime(n.DateTime)}</span>
          <div class="action-icons">
            ${infoBtn}
            ${starBtn}
            ${editBtn}
            <form method="POST" action="index.php" onsubmit="return confirm('Delete this note?')" style="display:contents">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="${n.Id}">
              <button type="submit" class="icon-btn del-btn" title="Delete note">🗑</button>
            </form>
          </div>
        </div>
      </div>`;
  }

  // Track the date of the last note shown (for date separators)
  let lastShownDate = <?= $count > 0 ? json_encode(substr(end($notes)['DateTime'], 0, 10)) : '""' ?>;

  async function pollNewNotes() {
    try {
      const res  = await fetch(`fetch_notes.php?last_id=${lastNoteId}`);
      if (res.status === 401) { location.href = 'login.php'; return; }
      const data = await res.json();
      if (!data.ok || !data.notes.length) return;

      const bottom   = document.getElementById('bottom');
      const wasAtBot = isAtBottom();

      // Remove empty state if present
      const empty = chat.querySelector('.empty-state');
      if (empty) empty.remove();

      data.notes.forEach(n => {
        // Date separator if day changed
        const noteDay = noteDate(n.DateTime);
        if (noteDay !== lastShownDate) {
          lastShownDate = noteDay;
          const sep = document.createElement('div');
          sep.className = 'date-sep';
          sep.textContent = dateLabel(n.DateTime);
          chat.insertBefore(sep, bottom);
        }

        // Insert note
        const div = document.createElement('div');
        div.innerHTML = buildNoteHTML(n).trim();
        chat.insertBefore(div.firstElementChild, bottom);

        // Add to search index
        notesData.push({ id: parseInt(n.Id), text: n.Note, datetime: n.DateTime, category: n.Category || 'general', attachments: n.Attachments || [] });

        lastNoteId = Math.max(lastNoteId, parseInt(n.Id));
        noteCount++;
      });

      // Update count badge
      document.querySelector('.note-count').textContent =
        noteCount + ' note' + (noteCount !== 1 ? 's' : '');

      // Apply active filter to newly inserted notes
      applyFilter();

      // Scroll or show button
      if (wasAtBot) {
        chat.scrollTo({ top: chat.scrollHeight, behavior: 'smooth' });
      } else {
        newNoteBtn.style.display = 'block';
        requestAnimationFrame(() => newNoteBtn.classList.add('visible'));
      }

    } catch (e) { /* silent — no internet blip should crash the UI */ }
  }

  // Hide new-notes button when user manually scrolls to bottom
  chat.addEventListener('scroll', () => {
    if (isAtBottom()) {
      newNoteBtn.classList.remove('visible');
      setTimeout(() => { if (!newNoteBtn.classList.contains('visible')) newNoteBtn.style.display = 'none'; }, 300);
    }
  });

  // Poll every 4 seconds
  setInterval(pollNewNotes, 4000);

  // ── PWA: Service Worker registration ───────────────────────────────────────
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js')
      .catch(err => console.warn('SW registration failed:', err));
  }

  // ── PWA: Install button ─────────────────────────────────────────────────────
  let deferredPrompt;
  const installBtn = document.getElementById('installBtn');

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installBtn.style.display = 'flex';
  });

  installBtn.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === 'accepted') {
      installBtn.style.display = 'none';
    }
    deferredPrompt = null;
  });

  // Hide install button once app is installed
  window.addEventListener('appinstalled', () => {
    installBtn.style.display = 'none';
    deferredPrompt = null;
  });

  // ── Escape attribute value ──────────────────────────────────────────────────
  function escapeAttr(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // ── Info tooltip: tap to toggle + edit click handler (delegated) ────────────
  document.addEventListener('click', (e) => {
    // Info button
    const infoBtn = e.target.closest('.info-btn');
    if (infoBtn) {
      e.stopPropagation();
      const wrap   = infoBtn.closest('.info-wrap');
      const isOpen = wrap.classList.contains('show');
      document.querySelectorAll('.info-wrap.show').forEach(w => w.classList.remove('show'));
      if (!isOpen) wrap.classList.add('show');
      return;
    }
    // Star button
    const starBtn = e.target.closest('.star-btn');
    if (starBtn) {
      e.stopPropagation();
      toggleStar(parseInt(starBtn.dataset.id), starBtn);
      return;
    }
    // Edit button
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
      e.stopPropagation();
      openEditModal(
        parseInt(editBtn.dataset.id),
        editBtn.dataset.note,
        editBtn.dataset.dt
      );
      return;
    }
    // Click outside closes tooltips
    if (!e.target.closest('.info-wrap')) {
      document.querySelectorAll('.info-wrap.show').forEach(w => w.classList.remove('show'));
    }
  });

  // ── Star toggle ─────────────────────────────────────────────────────────────
  async function toggleStar(id, btn) {
    const fd = new FormData();
    fd.append('action', 'star');
    fd.append('id', id);
    try {
      const res  = await fetch('index.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        const isNowStar = data.category === 'star';
        const wrapper   = document.getElementById('note-' + id);
        if (wrapper) {
          wrapper.dataset.category = data.category;
          const sb = wrapper.querySelector('.star-btn');
          if (sb) {
            sb.classList.toggle('starred', isNowStar);
            sb.title       = isNowStar ? 'Unstar note' : 'Star note';
            sb.textContent = isNowStar ? '⭐' : '☆';
          }
        }
        const idx = notesData.findIndex(n => n.id === id);
        if (idx >= 0) notesData[idx].category = data.category;
        applyFilter();
      }
    } catch (err) { console.error('Star toggle failed:', err); }
  }

  // ── Filter tabs ─────────────────────────────────────────────────────────────
  let activeFilter = 'all';

  document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      activeFilter = tab.dataset.filter;
      document.querySelectorAll('.filter-tab').forEach(t => t.classList.toggle('active', t === tab));
      applyFilter();
    });
  });

  function applyFilter() {
    let visible = 0;
    document.querySelectorAll('.note-wrapper').forEach(w => {
      const cat  = w.dataset.category || 'general';
      const show = activeFilter === 'all' || cat === activeFilter;
      w.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    manageDateSeparators();

    const fe  = document.getElementById('filterEmpty');
    const msg = document.getElementById('filterEmptyMsg');
    if (visible === 0 && noteCount > 0) {
      fe.style.display = 'flex';
      msg.textContent  = activeFilter === 'star'
        ? 'No starred notes yet. Tap ☆ on any note to star it.'
        : 'No notes in this category.';
    } else {
      fe.style.display = 'none';
    }
  }

  function manageDateSeparators() {
    document.querySelectorAll('.date-sep').forEach(sep => {
      let sib  = sep.nextElementSibling;
      let seen = false;
      while (sib && !sib.classList.contains('date-sep')) {
        if (sib.classList.contains('note-wrapper') && sib.style.display !== 'none') {
          seen = true; break;
        }
        sib = sib.nextElementSibling;
      }
      sep.style.display = seen ? '' : 'none';
    });
  }

  // ── Edit modal ──────────────────────────────────────────────────────────────
  let editingId = null;

  function openEditModal(id, note, dt) {
    editingId = id;
    document.getElementById('editText').value = note;
    // Convert 'YYYY-MM-DD HH:MM:SS' → 'YYYY-MM-DDTHH:MM' for datetime-local input
    document.getElementById('editDatetime').value = dt.replace(' ', 'T').slice(0, 16);
    document.getElementById('editModal').style.display = 'flex';
    setTimeout(() => document.getElementById('editText').focus(), 50);
  }

  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    editingId = null;
  }

  function handleModalOverlayClick(e) {
    if (e.target === document.getElementById('editModal')) closeEditModal();
  }

  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('editModal').style.display !== 'none') {
      closeEditModal();
    }
  });

  async function saveEdit() {
    if (!editingId) return;
    const note = document.getElementById('editText').value.trim();
    if (!note) return;
    const dtRaw = document.getElementById('editDatetime').value; // 'YYYY-MM-DDTHH:MM'
    const dt    = dtRaw ? dtRaw.replace('T', ' ') + ':00' : '';

    const saveBtn = document.getElementById('saveEditBtn');
    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving…';

    try {
      const fd = new FormData();
      fd.append('action',   'edit');
      fd.append('id',       editingId);
      fd.append('note',     note);
      fd.append('datetime', dt);

      const res  = await fetch('index.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.ok) {
        const wrapper = document.getElementById('note-' + editingId);
        if (wrapper) {
          // Update bubble text
          wrapper.querySelector('.note-bubble').innerHTML =
            escapeHtml(note).replace(/\n/g, '<br>');

          // Update displayed time
          if (dt) {
            const d = new Date(dt.replace(' ', 'T'));
            wrapper.querySelector('.note-time').textContent =
              d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
          }

          // Update edit-btn data attributes for future edits
          const eb = wrapper.querySelector('.edit-btn');
          if (eb) {
            eb.dataset.note = note;
            if (dt) eb.dataset.dt = dt;
          }

          // Update search index
          const idx = notesData.findIndex(n => n.id === editingId);
          if (idx >= 0) {
            notesData[idx].text     = note;
            if (dt) notesData[idx].datetime = dt;
          }
        }
        closeEditModal();
      }
    } catch (err) {
      console.error('Edit save failed:', err);
    } finally {
      saveBtn.disabled    = false;
      saveBtn.textContent = 'Save Changes';
    }
  }

  // ── Copy selected text tooltip ──────────────────────────────────────────────
  const copyBtn = document.createElement('div');
  copyBtn.id = 'copyTooltip';
  copyBtn.textContent = '📋 Copy';
  document.body.appendChild(copyBtn);

  let copyHideTimer;

  function showCopyTooltip(rect) {
    clearTimeout(copyHideTimer);
    const top  = rect.top - 48;
    const left = Math.min(
      Math.max(rect.left + rect.width / 2, 60),
      window.innerWidth - 60
    );
    copyBtn.style.top  = Math.max(8, top) + 'px';
    copyBtn.style.left = left + 'px';
    copyBtn.textContent = '📋 Copy';
    copyBtn.classList.remove('copied');
    copyBtn.style.display = 'block';
    requestAnimationFrame(() => copyBtn.classList.add('visible'));
  }

  function hideCopyTooltip(delay = 0) {
    clearTimeout(copyHideTimer);
    copyHideTimer = setTimeout(() => {
      copyBtn.classList.remove('visible', 'copied');
      setTimeout(() => { copyBtn.style.display = 'none'; }, 150);
    }, delay);
  }

  function getSelectionInBubble() {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return null;
    const text = sel.toString().trim();
    if (!text) return null;

    // Walk up the DOM to check if selection starts inside a .note-bubble
    let node = sel.anchorNode;
    while (node && node !== document.body) {
      if (node.classList && node.classList.contains('note-bubble')) {
        return { text, rect: sel.getRangeAt(0).getBoundingClientRect() };
      }
      node = node.parentNode;
    }
    return null;
  }

  // Show tooltip on mouse release
  document.addEventListener('mouseup', (e) => {
    if (copyBtn.contains(e.target)) return;
    setTimeout(() => {
      const result = getSelectionInBubble();
      result ? showCopyTooltip(result.rect) : hideCopyTooltip();
    }, 10);
  });

  // Show tooltip on touch release (mobile)
  document.addEventListener('touchend', (e) => {
    if (copyBtn.contains(e.target)) return;
    setTimeout(() => {
      const result = getSelectionInBubble();
      result ? showCopyTooltip(result.rect) : hideCopyTooltip();
    }, 120);
  });

  // Prevent tooltip click from clearing the selection
  copyBtn.addEventListener('mousedown', (e) => e.preventDefault());

  // Copy on click
  copyBtn.addEventListener('click', () => {
    const result = getSelectionInBubble();
    if (!result) { hideCopyTooltip(); return; }

    const finish = (ok) => {
      if (ok) {
        copyBtn.textContent = '✓ Copied!';
        copyBtn.classList.add('copied');
        hideCopyTooltip(1600);
        setTimeout(() => window.getSelection()?.removeAllRanges(), 1600);
      } else {
        copyBtn.textContent = '❌ Failed';
        hideCopyTooltip(1600);
      }
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(result.text)
        .then(() => finish(true))
        .catch(() => finish(false));
    } else {
      // Fallback for HTTP or older browsers
      try {
        document.execCommand('copy');
        finish(true);
      } catch (_) {
        finish(false);
      }
    }
  });

  // Hide when selection is cleared
  document.addEventListener('selectionchange', () => {
    if (!window.getSelection()?.toString().trim()) {
      hideCopyTooltip(200);
    }
  });

  // ── File attachments ────────────────────────────────────────────────────────
  const ALLOWED_TYPES = [
    'image/jpeg','image/png','image/gif','image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain','text/csv','application/json',
  ];
  const MAX_ATTACH    = 5;
  const MAX_BYTES     = 20 * 1024 * 1024;

  let pendingFiles = []; // [{file, previewURL}]

  const attachBtn   = document.getElementById('attachBtn');
  const fileInput   = document.getElementById('fileInput');
  const attachStrip = document.getElementById('attachStrip');
  const uploadError = document.getElementById('uploadError');
  const inputArea   = document.getElementById('inputArea');
  const noteForm    = document.getElementById('noteForm');

  attachBtn.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    addFiles(Array.from(fileInput.files));
    fileInput.value = '';
  });

  function addFiles(files) {
    let errMsg = '';
    for (const f of files) {
      if (pendingFiles.length >= MAX_ATTACH) { errMsg = `Max ${MAX_ATTACH} files per note.`; break; }
      if (!ALLOWED_TYPES.includes(f.type))   { errMsg = `"${f.name}" — type not allowed.`; continue; }
      if (f.size > MAX_BYTES)                { errMsg = `"${f.name}" — max 20 MB per file.`; continue; }
      const url = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
      pendingFiles.push({ file: f, previewURL: url });
    }
    showUploadError(errMsg);
    renderAttachStrip();
  }

  function removeFile(idx) {
    if (pendingFiles[idx]?.previewURL) URL.revokeObjectURL(pendingFiles[idx].previewURL);
    pendingFiles.splice(idx, 1);
    renderAttachStrip();
  }

  function renderAttachStrip() {
    if (!pendingFiles.length) {
      attachStrip.style.display = 'none';
      attachStrip.innerHTML = '';
      return;
    }
    attachStrip.style.display = 'flex';
    attachStrip.innerHTML = pendingFiles.map((pf, i) => {
      if (pf.previewURL) {
        return `<div class="attach-preview-img">
          <img src="${pf.previewURL}" alt="${escapeHtml(pf.file.name)}">
          <button type="button" class="attach-remove" onclick="removeFile(${i})" title="Remove">✕</button>
        </div>`;
      }
      return `<div class="attach-preview-file">
        <span>${jsFileIcon(pf.file.type)}</span>
        <span class="attach-preview-name" title="${escapeHtml(pf.file.name)}">${escapeHtml(pf.file.name)}</span>
        <button type="button" class="attach-remove" style="position:static;background:none;color:#ef4444;font-size:0.75rem;width:auto;height:auto;border-radius:0;" onclick="removeFile(${i})" title="Remove">✕</button>
      </div>`;
    }).join('');
  }

  function showUploadError(msg) {
    uploadError.textContent = msg;
    uploadError.style.display = msg ? 'block' : 'none';
  }

  // Paste image from clipboard
  ta.addEventListener('paste', (e) => {
    const items = Array.from(e.clipboardData?.items || []);
    const imageItems = items.filter(it => it.kind === 'file' && it.type.startsWith('image/'));
    if (!imageItems.length) return;
    e.preventDefault();
    addFiles(imageItems.map(it => it.getAsFile()));
  });

  // Drag and drop files onto input area
  inputArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    inputArea.classList.add('drag-over');
  });
  inputArea.addEventListener('dragleave', (e) => {
    if (!inputArea.contains(e.relatedTarget)) inputArea.classList.remove('drag-over');
  });
  inputArea.addEventListener('drop', (e) => {
    e.preventDefault();
    inputArea.classList.remove('drag-over');
    const files = Array.from(e.dataTransfer.files);
    if (files.length) addFiles(files);
  });

  // ── AJAX note submission ─────────────────────────────────────────────────────
  noteForm.addEventListener('submit', (e) => { e.preventDefault(); submitNote(); });

  async function submitNote() {
    const text = ta.value.trim();
    if (!text && !pendingFiles.length) return;

    const sendBtn = noteForm.querySelector('.send-btn');
    sendBtn.disabled  = true;
    sendBtn.innerHTML = '…';
    showUploadError('');

    try {
      const fd = new FormData();
      fd.append('action', 'add');
      fd.append('note',   ta.value.trim());
      pendingFiles.forEach(pf => fd.append('files[]', pf.file));

      const res  = await fetch('index.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (!data.ok) {
        showUploadError(data.error || 'Failed to save note.');
        return;
      }

      // Clear inputs
      ta.value = '';
      ta.style.height = 'auto';
      pendingFiles.forEach(pf => { if (pf.previewURL) URL.revokeObjectURL(pf.previewURL); });
      pendingFiles = [];
      renderAttachStrip();

      // Render new note immediately (don't wait for the 4s poll)
      const n      = data.note;
      const bottom = document.getElementById('bottom');
      const chat2  = document.getElementById('chatArea');

      const empty = chat2.querySelector('.empty-state');
      if (empty) empty.remove();

      const noteDay = noteDate(n.DateTime);
      if (noteDay !== lastShownDate) {
        lastShownDate = noteDay;
        const sep = document.createElement('div');
        sep.className   = 'date-sep';
        sep.textContent = dateLabel(n.DateTime);
        chat2.insertBefore(sep, bottom);
      }

      const wrapper = document.createElement('div');
      wrapper.innerHTML = buildNoteHTML(n).trim();
      chat2.insertBefore(wrapper.firstElementChild, bottom);

      notesData.push({ id: parseInt(n.Id), text: n.Note, datetime: n.DateTime, category: n.Category || 'general', attachments: n.Attachments || [] });
      lastNoteId = Math.max(lastNoteId, parseInt(n.Id));
      noteCount++;

      document.querySelector('.note-count').textContent =
        noteCount + ' note' + (noteCount !== 1 ? 's' : '');

      applyFilter();
      chat2.scrollTo({ top: chat2.scrollHeight, behavior: 'smooth' });

    } catch (err) {
      showUploadError('Network error — please try again.');
      console.error('submitNote failed:', err);
    } finally {
      sendBtn.disabled  = false;
      sendBtn.innerHTML = '&#9658;';
    }
  }

  // ── Lightbox ─────────────────────────────────────────────────────────────────
  const lightbox    = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightboxImg');
  const lightboxCap = document.getElementById('lightboxCaption');

  function openLightbox(src, caption) {
    lightboxImg.src    = src;
    lightboxImg.alt    = caption;
    lightboxCap.textContent = caption;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove('open');
    lightboxImg.src = '';
    document.body.style.overflow = '';
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && lightbox.classList.contains('open')) closeLightbox();
  });
</script>
</body>
</html>

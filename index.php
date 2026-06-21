<?php
require_once 'config.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$postError = '';

// Delete note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) deleteNote($id);
    header('Location: index.php');
    exit;
}

// Edit note (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id   = (int)($_POST['id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $dt   = trim($_POST['datetime'] ?? '');
    if ($id > 0 && $note !== '') {
        $dt = ($dt !== '') ? $dt : date('Y-m-d H:i:s');
        updateNote($id, $note, $dt);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Add note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    $note = trim($_POST['note'] ?? '');
    if ($note !== '') {
        insertNote($note, getClientIP(), getBrowser(), getLocation(getClientIP()));
        header('Location: index.php');
        exit;
    } else {
        $postError = 'Note cannot be empty.';
    }
}

$notes     = fetchNotes();
$count     = count($notes);
$bodyClass = (BUBBLE_ALIGN === 'left') ? 'bubbles-left' : 'bubbles-right';
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
    --bg:      #f0f2f7;
    --surface: #ffffff;
    --border:  rgba(0,0,0,0.08);
    --accent:  #6c63ff;
    --text:    #1e1e2e;
    --muted:   #8890a4;
    --bubble:  linear-gradient(135deg, #6c63ff, #8b5cf6);
    --radius:  18px;
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
    background: rgba(108,99,255,0.1);
    border: 1px solid rgba(108,99,255,0.25);
    color: #6c63ff;
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
    background: rgba(108,99,255,0.1);
    border: 1px solid rgba(108,99,255,0.3);
    color: #6c63ff;
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
  #installBtn:hover { background: rgba(108,99,255,0.18); }

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
    background: var(--bg);
    border: 1.5px solid rgba(0,0,0,0.1);
    border-radius: 12px;
    padding: 0 14px;
    gap: 10px;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .search-inner:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
    background: #fff;
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
    background: rgba(108,99,255,0.1);
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
    background: #fff;
    border: 1px solid rgba(0,0,0,0.1);
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
  .search-result-item:hover  { background: #f5f3ff; }
  .search-result-item:last-child { border-bottom: none; }
  .search-result-item.focused { background: #f5f3ff; }

  .result-num {
    width: 22px; height: 22px;
    background: rgba(108,99,255,0.12);
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
    background: rgba(108,99,255,0.18);
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
    background: var(--bubble);
    color: #fff;
    padding: 14px 18px;
    border-radius: var(--radius) var(--radius) 4px var(--radius);
    font-size: 0.95rem;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    box-shadow: 0 4px 18px rgba(108,99,255,0.25);
    transition: box-shadow 0.3s;
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
    0%   { box-shadow: 0 0 0 0   rgba(108,99,255,0.8); transform: scale(1); }
    20%  { box-shadow: 0 0 0 10px rgba(108,99,255,0.2); transform: scale(1.02); }
    100% { box-shadow: 0 4px 18px rgba(108,99,255,0.25); transform: scale(1); }
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
  .info-btn:hover { background: rgba(108,99,255,0.1) !important; }

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
    background: var(--bg);
    border: 1.5px solid rgba(0,0,0,0.1);
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
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
  }
  textarea::placeholder { color: var(--muted); }

  button.send-btn {
    background: var(--bubble);
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
    box-shadow: 0 4px 14px rgba(108,99,255,0.35);
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
    background: linear-gradient(135deg, #6c63ff, #8b5cf6);
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 10px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    box-shadow: 0 6px 24px rgba(108,99,255,0.45);
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
    .chat-area    { padding: 16px 10px 10px; }
    .input-area   { padding: 10px; }
  }

  @media (min-width: 900px) {
    .chat-area  { padding: 28px 12%; }
    .input-area { padding: 16px 12%; }
    .search-bar { padding: 10px 12%; }
    .search-results { left: 12%; right: 12%; }
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
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
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
    background: var(--bg);
    border: 1.5px solid rgba(0,0,0,0.1);
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 0.9rem;
    font-family: inherit;
    color: var(--text);
    outline: none;
    transition: border-color 0.2s, background 0.2s;
  }
  .edit-dt-input:focus {
    border-color: var(--accent);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
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
    background: linear-gradient(135deg, #6c63ff, #8b5cf6);
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-size: 0.88rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    color: #fff;
    transition: opacity 0.2s;
    box-shadow: 0 4px 14px rgba(108,99,255,0.3);
  }
  .btn-modal-save:hover   { opacity: 0.88; }
  .btn-modal-save:active  { transform: scale(0.97); }
  .btn-modal-save:disabled { opacity: 0.6; cursor: not-allowed; }
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

    <div class="note-wrapper" id="note-<?= (int)$note['Id'] ?>">
      <div class="note-bubble"><?= nl2br(htmlspecialchars($note['Note'])) ?></div>

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

<!-- Input area -->
<div class="input-area">
  <?php if ($postError): ?>
    <div class="error-banner"><?= htmlspecialchars($postError) ?></div>
  <?php endif; ?>

  <form method="POST" action="index.php" class="input-inner" id="noteForm">
    <textarea
      id="noteInput"
      name="note"
      placeholder="Write a note… (Shift+Enter for new line, Enter to send)"
      rows="1"
      autofocus
    ></textarea>
    <button type="submit" class="send-btn" title="Send note">&#9658;</button>
  </form>
  <p class="app-footer">Made with ❤️ by <a href="https://digital18.in" target="_blank" rel="noopener">DIGITAL18.IN</a></p>
</div>

<script>
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
      if (ta.value.trim()) document.getElementById('noteForm').submit();
    }
  });

  // ── Notes index for search ──────────────────
  const notesData = [
    <?php foreach ($notes as $n): ?>
    {
      id:       <?= (int)$n['Id'] ?>,
      text:     <?= json_encode($n['Note']) ?>,
      datetime: <?= json_encode($n['DateTime']) ?>
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

  function buildNoteHTML(n) {
    const noteText = escapeHtml(n.Note).replace(/\n/g, '<br>');

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

    const editBtn = `<button type="button" class="icon-btn edit-btn" title="Edit note"
      data-id="${n.Id}"
      data-note="${escapeAttr(n.Note)}"
      data-dt="${escapeAttr(n.DateTime)}">✏️</button>`;

    return `
      <div class="note-wrapper" id="note-${n.Id}">
        <div class="note-bubble">${noteText}</div>
        <div class="note-actions">
          <span class="note-time">${formatTime(n.DateTime)}</span>
          <div class="action-icons">
            ${infoBtn}
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
        notesData.push({ id: parseInt(n.Id), text: n.Note, datetime: n.DateTime });

        lastNoteId = Math.max(lastNoteId, parseInt(n.Id));
        noteCount++;
      });

      // Update count badge
      document.querySelector('.note-count').textContent =
        noteCount + ' note' + (noteCount !== 1 ? 's' : '');

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
</script>
</body>
</html>

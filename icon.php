<?php
// Generates PNG icon using GD — used for apple-touch-icon and PWA manifest PNG fallback
// Cache for 1 year
header('Cache-Control: public, max-age=31536000, immutable');

$size = (int)($_GET['size'] ?? 192);
$size = in_array($size, [72, 96, 128, 144, 152, 192, 384, 512]) ? $size : 192;

if (!extension_loaded('gd')) {
    // GD not available — redirect to SVG
    header('Location: icon.svg');
    exit;
}

header('Content-Type: image/png');

$img = imagecreatetruecolor($size, $size);
imagesavealpha($img, true);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

$s = $size / 512.0; // scale factor

// ── Helper: filled rounded rectangle ─────────────────────────────────────────
function fillRoundRect($img, $x1, $y1, $x2, $y2, $r, $color) {
    $r = max(1, $r);
    imagefilledrectangle($img, $x1 + $r, $y1,      $x2 - $r, $y2,      $color);
    imagefilledrectangle($img, $x1,      $y1 + $r,  $x2,      $y2 - $r, $color);
    imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

// ── Background (purple) ───────────────────────────────────────────────────────
$purple = imagecolorallocate($img, 108, 99, 255);
fillRoundRect($img, 0, 0, $size - 1, $size - 1, (int)(115 * $s), $purple);

// Gradient effect (lighter top-left strip)
$light = imagecolorallocatealpha($img, 167, 139, 250, 80);
fillRoundRect($img, 0, 0, (int)($size * 0.6), (int)($size * 0.6), (int)(115 * $s), $light);

// ── Notepad body (white) ──────────────────────────────────────────────────────
$white = imagecolorallocate($img, 255, 255, 255);
fillRoundRect($img,
    (int)(132 * $s), (int)(114 * $s),
    (int)(382 * $s), (int)(432 * $s),
    (int)(22 * $s), $white
);

// ── Text lines ────────────────────────────────────────────────────────────────
$line1 = imagecolorallocatealpha($img, 108, 99, 255, 60);
$line2 = imagecolorallocatealpha($img, 108, 99, 255, 80);
$line3 = imagecolorallocatealpha($img, 108, 99, 255, 100);

$lines = [
    [(int)(172*$s), (int)(192*$s), (int)(348*$s), (int)(206*$s), $line3],
    [(int)(172*$s), (int)(228*$s), (int)(348*$s), (int)(242*$s), $line3],
    [(int)(172*$s), (int)(264*$s), (int)(348*$s), (int)(278*$s), $line3],
    [(int)(172*$s), (int)(300*$s), (int)(300*$s), (int)(314*$s), $line1],
    [(int)(172*$s), (int)(336*$s), (int)(240*$s), (int)(350*$s), $line2],
];
foreach ($lines as [$lx1, $ly1, $lx2, $ly2, $lc]) {
    fillRoundRect($img, $lx1, $ly1, $lx2, $ly2, (int)(7 * $s), $lc);
}

// ── Clip (top centre) ────────────────────────────────────────────────────────
$clipCol = imagecolorallocate($img, 139, 92, 246);
fillRoundRect($img,
    (int)(208 * $s), (int)(76 * $s),
    (int)(298 * $s), (int)(136 * $s),
    (int)(12 * $s), $clipCol
);

// Clip hole
$holeWhite = imagecolorallocatealpha($img, 255, 255, 255, 60);
$cx = (int)(253 * $s); $cy = (int)(106 * $s);
imagefilledellipse($img, $cx, $cy, (int)(28 * $s), (int)(28 * $s), $holeWhite);
imagefilledellipse($img, $cx, $cy, (int)(14 * $s), (int)(14 * $s), $clipCol);

imagepng($img);
imagedestroy($img);

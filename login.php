<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!empty($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $captcha  = (int)($_POST['captcha'] ?? -1);
    $answer   = $_SESSION['captcha_answer'] ?? null;

    if ($answer === null || $captcha !== $answer) {
        $error = 'Incorrect CAPTCHA. Please try again.';
    } elseif (!checkPassword($password)) {
        $error = 'Wrong password. Please try again.';
    } else {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit;
    }
}

// Fresh CAPTCHA every load
$a = rand(2, 9);
$b = rand(1, 9);
$_SESSION['captcha_answer'] = $a + $b;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — D18 Notes</title>

<!-- PWA -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#6c63ff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="D18 Notes">

<!-- Icons -->
<link rel="icon" type="image/svg+xml" href="icon.svg">
<link rel="apple-touch-icon" href="icon.php?size=192">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f0f2f7;
    padding: 20px;
    gap: 16px;
  }

  .app-footer {
    font-size: 0.7rem;
    color: #8890a4;
    letter-spacing: 0.2px;
    user-select: none;
  }
  .app-footer a {
    color: #6c63ff;
    text-decoration: none;
    font-weight: 600;
    transition: opacity 0.15s;
  }
  .app-footer a:hover { opacity: 0.75; }

  .card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 24px;
    padding: 48px 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.1);
  }

  .logo {
    text-align: center;
    margin-bottom: 8px;
    font-size: 3rem;
  }

  h1 {
    text-align: center;
    color: #1e1e2e;
    font-size: 1.7rem;
    font-weight: 700;
    margin-bottom: 6px;
  }

  .subtitle {
    text-align: center;
    color: #8890a4;
    font-size: 0.875rem;
    margin-bottom: 36px;
  }

  .field { margin-bottom: 20px; }

  label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #8890a4;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
  }

  input[type="password"],
  input[type="number"] {
    width: 100%;
    background: #f0f2f7;
    border: 1.5px solid rgba(0,0,0,0.1);
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 1rem;
    font-family: inherit;
    color: #1e1e2e;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
  }

  input[type="password"]:focus,
  input[type="number"]:focus {
    border-color: #6c63ff;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,99,255,0.1);
  }

  input::placeholder { color: #b0b8cc; }

  .captcha-display {
    background: rgba(108,99,255,0.08);
    border: 1.5px solid rgba(108,99,255,0.25);
    border-radius: 12px;
    padding: 14px 16px;
    text-align: center;
    font-size: 1.2rem;
    font-weight: 700;
    color: #6c63ff;
    letter-spacing: 3px;
    margin-bottom: 10px;
    user-select: none;
  }

  .alert {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.25);
    color: #ef4444;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.875rem;
    margin-bottom: 20px;
  }

  button[type="submit"] {
    width: 100%;
    background: linear-gradient(135deg, #6c63ff, #8b5cf6);
    border: none;
    border-radius: 12px;
    padding: 15px;
    font-size: 1rem;
    font-weight: 600;
    font-family: inherit;
    color: #fff;
    cursor: pointer;
    margin-top: 8px;
    transition: opacity 0.2s, transform 0.1s;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 18px rgba(108,99,255,0.35);
  }

  button[type="submit"]:hover  { opacity: 0.9; }
  button[type="submit"]:active { transform: scale(0.98); }

  @media (max-width: 480px) {
    .card { padding: 36px 24px; }
  }
</style>
</head>
<body>
<div class="card">
  <div class="logo">📝</div>
  <h1>D18 Notes</h1>
  <p class="subtitle">Sign in to access your personal notes</p>

  <?php if ($error): ?>
    <div class="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php" autocomplete="off">
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autofocus placeholder="Enter your password">
    </div>

    <div class="field">
      <label>Security Check</label>
      <div class="captcha-display">What is <?= $a ?> + <?= $b ?> ?</div>
      <input type="number" name="captcha" required placeholder="Type the answer here" min="0" max="99">
    </div>

    <button type="submit">Login →</button>
  </form>
</div>

<p class="app-footer">Made with ❤️ by <a href="https://digital18.in" target="_blank" rel="noopener">DIGITAL18.IN</a></p>

<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js')
      .catch(err => console.warn('SW:', err));
  }
</script>
</body>
</html>

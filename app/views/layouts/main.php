<?php use App\Lib\Auth; use App\Lib\Csrf; ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#4b2e2b">
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/assets/css/app.css">
  <title><?= htmlspecialchars(config('app.name', 'Coffee Loyalty')) ?></title>
</head>
<body>
<header>
  <a href="/">☕ <?= htmlspecialchars(config('app.name', 'Coffee Loyalty')) ?></a>
  <nav>
    <?php if (Auth::user()): ?>
      <a href="/profile">Профиль</a>
      <a href="/logout">Выход</a>
    <?php else: ?>
      <a href="/auth">Вход</a>
    <?php endif; ?>
  </nav>
</header>
<main>
  <?php require $templatePath; ?>
</main>
<button id="installBtn" hidden>Установить приложение</button>
<script>window.CSRF_TOKEN='<?= Csrf::token() ?>';</script>
<script src="/assets/js/app.js"></script>
</body>
</html>

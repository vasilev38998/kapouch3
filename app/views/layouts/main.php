<?php use App\Lib\Auth; use App\Lib\Csrf; $u = Auth::user(); ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#ffd42a">
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/assets/css/app.css">
  <title><?= htmlspecialchars(config('app.name', 'Kapouch')) ?></title>
</head>
<body>
<header class="topbar fade-in">
  <a class="brand" href="/">KAPOUCH/</a>
  <nav class="topnav">
    <a href="/menu">Меню</a>
    <?php if ($u): ?>
      <a href="/profile">Кабинет</a>
      <?php if (in_array($u['role'], ['barista','manager','admin'], true)): ?><a href="/staff">Staff</a><?php endif; ?>
      <?php if (in_array($u['role'], ['manager','admin'], true)): ?><a href="/admin">Админка</a><?php endif; ?>
      <a href="/logout">Выход</a>
    <?php else: ?>
      <a href="/auth">Вход</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container"><?php require $templatePath; ?></main>
<footer class="footer fade-in">Kapouch · Шелехов, Култукский тракт 25/1</footer>
<button id="installBtn" class="install-btn" hidden>Установить Kapouch App</button>
<script>window.CSRF_TOKEN='<?= Csrf::token() ?>';window.APP_BASE='<?= htmlspecialchars(rtrim((string)config('app.base_url',''), '/')) ?>';window.WEB_PUSH_PUBLIC_KEY='<?= htmlspecialchars((string)config('web_push.public_key','')) ?>';</script>
<script src="/assets/js/app.js"></script>
</body>
</html>

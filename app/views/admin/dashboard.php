<h2>Админ-панель Kapouch</h2>
<section class="card admin-welcome fade-in">
  <strong>Kapouch</strong><br>
  Шелехов, Култукский тракт 25/1
</section>
<section class="card kpi-grid fade-in">
  <div><small>Пользователи</small><strong><?= (int)$stats['users_total'] ?></strong></div>
  <div><small>Заказы 30д</small><strong><?= (int)$stats['orders_30d'] ?></strong></div>
  <div><small>Кэшбэк 30д</small><strong><?= number_format((float)$stats['cashback_30d'],2,'.',' ') ?> ₽</strong></div>
  <div><small>Списано наград</small><strong><?= (int)$stats['rewards_30d'] ?></strong></div>
</section>

<section class="card kpi-grid fade-in">
  <div><small>OTP за 24ч</small><strong><?= (int)($health['otp_24h'] ?? 0) ?></strong></div>
  <div><small>OTP ошибок 24ч</small><strong><?= (int)($health['otp_fail_24h'] ?? 0) ?></strong></div>
  <div><small>Push кампаний 7д</small><strong><?= (int)($health['push_sent_7d'] ?? 0) ?></strong></div>
  <div><small>Push кликов 7д</small><strong><?= (int)($health['push_clicks_7d'] ?? 0) ?></strong></div>
  <div><small>Непрочитанных уведомлений</small><strong><?= (int)($health['unread_notifs'] ?? 0) ?></strong></div>
  <div><small>Позиции в стоп-листе</small><strong><?= (int)($health['menu_sold_out'] ?? 0) ?></strong></div>
</section>

<section class="card fade-in">
  <div class="row">
    <a class="btn" href="/admin/settings">Настройки</a>
    <a class="btn" href="/admin/users">Роли</a>
    <a class="btn" href="/admin/locations">Локации</a>
    <a class="btn" href="/admin/promocodes">Промокоды</a>
    <a class="btn" href="/admin/missions">Миссии</a>
    <a class="btn" href="/admin/menu">Меню</a>
    <a class="btn" href="/admin/push">Push</a>
    <a class="btn" href="/admin/data">Data manager</a>
    <a class="btn" href="/admin/exports">Отчёты</a>
    <a class="btn" href="/admin/audit">Аудит</a>
  </div>
</section>
<section class="card fade-in">
  <h3>Новые пользователи</h3>
  <?php foreach($recentUsers as $u): ?>
    <div>#<?= (int)$u['id'] ?> · <?= htmlspecialchars($u['phone']) ?> · <?= htmlspecialchars($u['role']) ?> · <?= htmlspecialchars($u['created_at']) ?></div>
  <?php endforeach; ?>
</section>

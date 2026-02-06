<h2>Экспорт / отчёты</h2>
<div class="card">
  <a href="/admin/exports?type=orders">CSV Заказы</a><br>
  <a href="/admin/exports?type=operations">CSV Операции</a><br>
  <a href="/admin/exports?type=users">CSV Пользователи</a>
</div>
<div class="card">
  <div>Кэшбэк начислено (30 дней): <?= htmlspecialchars((string)$report['cashback_earned']) ?></div>
  <div>Использовано наград: <?= htmlspecialchars((string)$report['rewards_used']) ?></div>
  <div>Активные пользователи: <?= htmlspecialchars((string)$report['active_users']) ?></div>
</div>

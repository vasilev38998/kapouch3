<h2>Поиск пользователя</h2>
<form class="card" method="get">
  <input name="phone" placeholder="+7...">
  <button class="btn">Найти</button>
</form>
<?php if ($found): ?><div class="card">ID: <?= (int)$found['id'] ?> · <?= htmlspecialchars($found['phone']) ?> · <a href="/staff/order/create?user_id=<?= (int)$found['id'] ?>">Заказ</a></div><?php endif; ?>

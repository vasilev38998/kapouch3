<h2>Поиск пользователя</h2>
<form class="card" method="get">
  <input name="phone" placeholder="+7..." value="<?= htmlspecialchars((string)($_GET['phone'] ?? '')) ?>">
  <button class="btn">Найти</button>
</form>
<?php if ($found): ?>
<div class="card">
  ID: <?= (int)$found['id'] ?> · <?= htmlspecialchars($found['phone']) ?> · <a href="/staff/order/create?user_id=<?= (int)$found['id'] ?>">Заказ</a><br>
  Штампы: <?= (int)($state['stamps'] ?? 0) ?> · Награда: <?= (int)($state['reward_available'] ?? 0) ? 'доступна' : 'нет' ?>
  <?php if ((int)($state['reward_available'] ?? 0) === 1): ?>
    <form method="post" action="/staff/reward/redeem" style="margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
      <input type="hidden" name="user_id" value="<?= (int)$found['id'] ?>">
      <input type="hidden" name="phone" value="<?= htmlspecialchars($found['phone']) ?>">
      <button class="btn">Списать награду 6/6</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

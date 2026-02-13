<h2>Заказ #<?= (int)($order['id'] ?? 0) ?></h2>
<?php if($order): ?>
<div class="card">
  Пользователь: <?= (int)$order['user_id'] ?><br>
  Сумма: <?= htmlspecialchars($order['total_amount']) ?><br>
  Статус: <?= htmlspecialchars($order['status']) ?><br>
  <form method="post" action="/staff/order/<?= (int)$order['id'] ?>/reverse" onsubmit="return confirm('Сделать реверс?')">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <button class="btn danger">Сделать reversal</button>
  </form>
</div>
<?php endif; ?>

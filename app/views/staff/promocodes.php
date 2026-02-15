<h2>Промокоды</h2>
<div class="card"><?php foreach($codes as $c): ?><div><?= htmlspecialchars($c['code']) ?> · <?= htmlspecialchars($c['type']) ?> · <?= htmlspecialchars((string)$c['value']) ?></div><?php endforeach; ?></div>

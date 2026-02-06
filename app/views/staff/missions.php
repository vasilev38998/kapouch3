<h2>Миссии</h2>
<div class="card"><?php foreach($missions as $m): ?><div><?= htmlspecialchars($m['name']) ?> · active=<?= (int)$m['is_active'] ?></div><?php endforeach; ?></div>

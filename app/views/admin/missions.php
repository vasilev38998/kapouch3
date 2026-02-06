<h2>Admin: Миссии</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input name="name" placeholder="Название" required>
  <input name="type" placeholder="orders_in_period" required>
  <textarea name="config_json" placeholder='{"orders_target":3}' required></textarea>
  <textarea name="reward_json" placeholder='{"type":"stamp","value":1}' required></textarea>
  <input name="starts_at" type="datetime-local">
  <input name="ends_at" type="datetime-local">
  <label><input type="checkbox" name="is_active" checked> active</label>
  <button class="btn">Создать</button>
</form>
<div class="card"><?php foreach($rows as $r): ?><div>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['name']) ?> · <?= htmlspecialchars($r['type']) ?> · active=<?= (int)$r['is_active'] ?></div><?php endforeach; ?></div>

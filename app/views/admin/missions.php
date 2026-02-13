<h2>Admin: Миссии</h2>
<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="action" value="create">
  <input name="name" placeholder="Название" required>
  <input name="type" placeholder="orders_in_period" required>
  <textarea name="config_json" placeholder='{"orders_target":3}' required></textarea>
  <textarea name="reward_json" placeholder='{"type":"stamp","value":1}' required></textarea>
  <input name="starts_at" type="datetime-local">
  <input name="ends_at" type="datetime-local">
  <label><input type="checkbox" name="is_active" checked> active</label>
  <button class="btn">Создать</button>
</form>
<div class="card fade-in">
  <?php foreach($rows as $r): ?>
    <form method="post" class="row" style="justify-content:space-between;align-items:center;border-bottom:1px dashed #e4be5f;padding:8px 0">
      <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="mission_id" value="<?= (int)$r['id'] ?>">
      <div>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['name']) ?> · <?= htmlspecialchars($r['type']) ?> · active=<?= (int)$r['is_active'] ?></div>
      <button class="btn ghost" type="submit">Переключить</button>
    </form>
  <?php endforeach; ?>
</div>

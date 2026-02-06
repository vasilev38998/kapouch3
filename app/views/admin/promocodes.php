<h2>Admin: Промокоды</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input name="code" placeholder="CODE" required>
  <select name="type">
    <?php foreach(['stamps','cashback_fixed','cashback_boost_percent','reward'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
  </select>
  <input name="value" type="number" step="0.01" placeholder="value" required>
  <input name="starts_at" type="datetime-local">
  <input name="ends_at" type="datetime-local">
  <input name="max_uses_total" type="number" placeholder="max total">
  <input name="max_uses_per_user" type="number" placeholder="max per user">
  <input name="min_order_amount" type="number" step="0.01" placeholder="min order">
  <input name="location_id" type="number" placeholder="location id">
  <label><input type="checkbox" name="is_active" checked> active</label>
  <textarea name="meta_json" placeholder='{"category":"coffee"}'></textarea>
  <button class="btn">Создать</button>
</form>
<div class="card"><?php foreach($rows as $r): ?><div>#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['code']) ?> · <?= htmlspecialchars($r['type']) ?> · <?= htmlspecialchars((string)$r['value']) ?> · active=<?= (int)$r['is_active'] ?></div><?php endforeach; ?></div>

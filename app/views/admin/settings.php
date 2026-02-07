<h2>Настройки</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <?php foreach($rows as $r): ?>
    <label><?= htmlspecialchars($r['key']) ?></label>
    <input name="<?= htmlspecialchars($r['key']) ?>" value="<?= htmlspecialchars((string)$r['value']) ?>">
  <?php endforeach; ?>
  <button class="btn">Сохранить</button>
</form>

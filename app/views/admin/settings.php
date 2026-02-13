<h2>Настройки системы</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <?php foreach(($meta ?? []) as $key => $m): ?>
    <label><?= htmlspecialchars((string)($m['label'] ?? $key)) ?></label>
    <input name="<?= htmlspecialchars((string)$key) ?>" value="<?= htmlspecialchars((string)($values[$key] ?? '')) ?>">
    <small class="muted">Ключ: <?= htmlspecialchars((string)$key) ?></small>
  <?php endforeach; ?>
  <button class="btn">Сохранить</button>
</form>

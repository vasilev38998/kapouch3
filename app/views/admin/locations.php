<h2>Локации</h2>
<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input name="name" placeholder="Название" required>
  <input name="address" placeholder="Адрес">
  <input name="url2gis" placeholder="2GIS URL">
  <input name="urly" placeholder="Yandex URL">
  <button class="btn">Добавить</button>
</form>
<div class="card fade-in">
  <?php foreach($locations as $l): ?>
    <form method="post" class="row" style="justify-content:space-between;align-items:center;border-bottom:1px dashed #e4be5f;padding:8px 0">
      <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="location_id" value="<?= (int)$l['id'] ?>">
      <div>#<?= (int)$l['id'] ?> <?= htmlspecialchars($l['name']) ?> · <?= htmlspecialchars((string)$l['address']) ?> · active=<?= (int)$l['is_active'] ?></div>
      <button class="btn ghost" type="submit">Переключить</button>
    </form>
  <?php endforeach; ?>
</div>

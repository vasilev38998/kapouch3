<h2>Локации</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input name="name" placeholder="Название" required>
  <input name="address" placeholder="Адрес">
  <input name="url2gis" placeholder="2GIS URL">
  <input name="urly" placeholder="Yandex URL">
  <button class="btn">Добавить</button>
</form>
<div class="card"><?php foreach($locations as $l): ?><div>#<?= (int)$l['id'] ?> <?= htmlspecialchars($l['name']) ?></div><?php endforeach; ?></div>

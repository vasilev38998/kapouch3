<h2>Меню Kapouch</h2>
<section class="card">
  <form method="get" class="row" style="align-items:end">
    <label style="flex:1">Категория
      <?php $cats = []; foreach(($items ?? []) as $it){$cats[(string)$it['category']] = true;} $cats=array_keys($cats); sort($cats); $currentCat=(string)($_GET['category'] ?? ''); ?>
      <select name="category">
        <option value="">Все категории</option>
        <?php foreach($cats as $cat): ?><option value="<?= htmlspecialchars($cat) ?>" <?= $currentCat===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php $showSoldOut = (($_GET['show_sold_out'] ?? '') === '1'); ?>
    <label><input type="checkbox" name="show_sold_out" value="1" <?= $showSoldOut?'checked':'' ?> style="width:auto"> Показать стоп-лист</label>
    <button class="btn">Применить</button>
  </form>
</section>
<section class="grid-2">
  <?php
    $filtered = [];
    foreach (($items ?? []) as $item) {
      if ($currentCat !== '' && (string)$item['category'] !== $currentCat) continue;
      if (!$showSoldOut && (int)($item['is_sold_out'] ?? 0) === 1) continue;
      $filtered[] = $item;
    }
  ?>
  <?php if (empty($filtered)): ?>
    <div class="card">Пока нет доступных позиций меню по выбранным фильтрам.</div>
  <?php endif; ?>
  <?php foreach ($filtered as $item): ?>
    <article class="card">
      <?php if (!empty($item['image_url'])): ?>
        <img src="<?= htmlspecialchars((string)$item['image_url']) ?>" alt="<?= htmlspecialchars((string)$item['name']) ?>" style="width:100%;max-height:220px;object-fit:cover;border-radius:12px;margin-bottom:8px">
      <?php endif; ?>
      <div class="row" style="justify-content:space-between;align-items:center">
        <small class="muted"><?= htmlspecialchars((string)$item['category']) ?></small>
        <?php if ((int)($item['is_sold_out'] ?? 0) === 1): ?><span class="chip">Стоп-лист</span><?php endif; ?>
      </div>
      <h3><?= htmlspecialchars((string)$item['name']) ?></h3>
      <div><strong><?= number_format((float)$item['price'], 2, '.', ' ') ?> ₽</strong></div>
      <?php if (!empty($item['description'])): ?><p class="muted"><?= htmlspecialchars((string)$item['description']) ?></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

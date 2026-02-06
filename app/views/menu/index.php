<h2>Меню Kapouch</h2>
<section class="grid-2">
  <?php if (empty($items)): ?>
    <div class="card">Пока нет доступных позиций меню.</div>
  <?php endif; ?>
  <?php foreach (($items ?? []) as $item): ?>
    <article class="card">
      <?php if (!empty($item['image_url'])): ?>
        <img src="<?= htmlspecialchars((string)$item['image_url']) ?>" alt="<?= htmlspecialchars((string)$item['name']) ?>" style="width:100%;max-height:220px;object-fit:cover;border-radius:12px;margin-bottom:8px">
      <?php endif; ?>
      <h3><?= htmlspecialchars((string)$item['name']) ?></h3>
      <div><strong><?= number_format((float)$item['price'], 2, '.', ' ') ?> ₽</strong></div>
      <?php if (!empty($item['description'])): ?><p class="muted"><?= htmlspecialchars((string)$item['description']) ?></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

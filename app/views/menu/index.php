<h2>Меню Kapouch</h2>
<section class="card menu-filters">
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
    <label class="fav-filter"><input type="checkbox" id="favoritesToggle" style="width:auto"> Только избранное</label>
    <button class="btn">Применить</button>
  </form>
  <div id="favoritesSummary" class="favorites-summary muted">Добавляйте любимые позиции в избранное, чтобы не искать их каждый раз.</div>
</section>
<section class="card" id="menuCart" data-menu-cart>
  <h3>Корзина</h3>
  <div id="menuCartList" class="muted">Добавьте позиции из меню.</div>
  <div class="menu-cart-total">Итого: <strong id="menuCartTotal">0.00 ₽</strong></div>
  <div class="row">
    <button class="btn" type="button" id="menuPayBtn" data-menu-pay>Оплатить через СБП Т‑Банк</button>
    <button class="btn ghost" type="button" id="menuCartClear">Очистить</button>
  </div>
  <small class="muted" id="menuPayStatus">Для оплаты нужен вход в аккаунт.</small>
</section>

<section class="grid-2" data-menu-list>
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
    <article class="card menu-card" data-menu-item data-menu-id="<?= (int)$item['id'] ?>" data-menu-name="<?= htmlspecialchars((string)$item['name']) ?>" data-menu-price="<?= number_format((float)$item['price'], 2, '.', '') ?>">
      <?php if (!empty($item['image_url'])): ?>
        <img src="<?= htmlspecialchars((string)$item['image_url']) ?>" alt="<?= htmlspecialchars((string)$item['name']) ?>" style="width:100%;max-height:220px;object-fit:cover;border-radius:12px;margin-bottom:8px">
      <?php endif; ?>
      <div class="row" style="justify-content:space-between;align-items:center">
        <small class="muted"><?= htmlspecialchars((string)$item['category']) ?></small>
        <?php if ((int)($item['is_sold_out'] ?? 0) === 1): ?><span class="chip">Стоп-лист</span><?php endif; ?>
      </div>
      <h3><?= htmlspecialchars((string)$item['name']) ?></h3>
      <div class="menu-card__price"><strong><?= number_format((float)$item['price'], 2, '.', ' ') ?> ₽</strong></div>
      <?php if (!empty($item['description'])): ?><p class="muted"><?= htmlspecialchars((string)$item['description']) ?></p><?php endif; ?>
      <div class="menu-qty">
        <button type="button" class="qty-btn" data-qty-minus>−</button>
        <input type="number" min="0" max="20" step="1" value="0" data-qty-input>
        <button type="button" class="qty-btn" data-qty-plus>+</button>
      </div>
      <div class="menu-card__actions">
        <button class="favorite-btn" type="button" data-favorite-toggle aria-pressed="false">
          <span class="favorite-icon" aria-hidden="true">❤</span>
          <span class="favorite-text">В избранное</span>
        </button>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<h2>Меню Kapouch</h2>
<section class="card menu-engagement fade-in">
  <div class="kpi-row">
    <div><small>Дневная серия</small><strong id="streakValue">0</strong></div>
    <div><small>Лаки-бонус</small><strong id="dailyBonusValue">—</strong></div>
  </div>
  <div class="row" style="margin-top:8px">
    <button class="btn ghost" type="button" id="dailyBonusBtn">🎁 Получить бонус дня</button>
    <button class="btn ghost" type="button" id="themeToggleBtn">🌗 Тема</button>
  </div>
  <small class="muted" id="engagementHint">Заходите ежедневно — открывайте новые бонусы и держите серию посещений.</small>
</section>
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

  <div class="row" style="margin-top:8px;align-items:center">
    <label style="flex:1">Поиск по меню
      <input id="menuSearch" type="search" placeholder="Например: капучино, десерт...">
    </label>
    <button class="btn ghost" type="button" id="menuRestoreLast" title="Быстро восстановить прошлую корзину">Повторить прошлый заказ</button>
  </div>
  <div class="row" style="margin-top:8px;align-items:end">
    <label style="flex:1">Мин. цена
      <input id="menuMinPrice" type="number" min="0" step="1" placeholder="0">
    </label>
    <label style="flex:1">Макс. цена
      <input id="menuMaxPrice" type="number" min="0" step="1" placeholder="9999">
    </label>
  </div>
  <div class="favorites-summary" id="luckyPickHint">🎯 Лаки-позиция дня: загрузка...</div>
  <div class="favorites-summary muted" id="recentMenuView">Вы еще не просматривали позиции — выберите что-нибудь интересное.</div>
  <div id="favoritesSummary" class="favorites-summary muted">Добавляйте любимые позиции в избранное, чтобы не искать их каждый раз.</div>
</section>
<section class="card" id="menuCart" data-menu-cart>
  <h3>Корзина</h3>
  <div id="menuCartList" class="muted">Добавьте позиции из меню.</div>
  <div class="menu-cart-total">Итого: <strong id="menuCartTotal">0.00 ₽</strong></div>
  <label>Списать кэшбэк</label>
  <input id="menuCashbackSpend" type="number" min="0" step="0.01" value="0">
  <small class="muted" id="menuCashbackHint">К оплате по СБП: 0.00 ₽</small>
  <small class="muted" id="menuEtaHint">Оценка готовности: —</small>
  <div class="row">
    <button class="btn" type="button" id="menuPayBtn" data-menu-pay>Оплатить через СБП Т‑Банк</button>
    <button class="btn ghost" type="button" id="menuCartClear">Очистить</button>
    <button class="btn ghost" type="button" id="menuCartShare">Поделиться корзиной</button>
  </div>
  <small class="muted" id="menuPayStatus">Для оплаты нужен вход в аккаунт.</small>
</section>

<section class="card" id="menuUpsell" hidden></section>

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
    <article class="card menu-card" data-menu-item data-menu-id="<?= (int)$item['id'] ?>" data-menu-name="<?= htmlspecialchars((string)$item['name']) ?>" data-menu-price="<?= number_format((float)$item['price'], 2, '.', '') ?>" data-menu-category="<?= htmlspecialchars((string)$item['category']) ?>" data-menu-description="<?= htmlspecialchars((string)($item['description'] ?? '')) ?>">
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

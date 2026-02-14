<h2>Личный кабинет Kapouch</h2>
<section class="card admin-welcome fade-in">
  <strong>Адрес кофейни:</strong> Шелехов, Култукский тракт 25/1
</section>
<section class="card widget fade-in" data-reward-available="<?= (int)$loyalty['reward_available'] ?>">
  <div class="kpi-row">
    <div><small>Баланс звёздочек</small><strong><?= number_format((float)$cashback, 2, '.', ' ') ?> ★</strong></div>
    <div><small>Рублёвый баланс</small><strong><?= number_format((float)($realBalance ?? 0), 2, '.', ' ') ?> ₽</strong></div>
    <div><small>Штампы</small><strong><?= (int)$loyalty['stamps'] ?>/6</strong></div>
  </div>
  <div class="stamps"><?php for($i=1;$i<=6;$i++): ?><span class="dot <?= $i <= (int)$loyalty['stamps'] ? 'filled':'' ?>"></span><?php endfor; ?></div>
  <?php if ((int)$loyalty['reward_available'] === 1): ?><div class="ok">Награда доступна 🎁</div><?php endif; ?>
  <div class="row">
    <a class="btn" href="/profile/qr">Код для штампов</a>
    <a class="btn ghost" href="/profile/invite">Пригласить друга</a>
    <?php if (in_array($user['role'], ['barista','manager','admin'], true)): ?><a class="btn ghost" href="/staff">Staff</a><?php endif; ?>
    <?php if (in_array($user['role'], ['manager','admin'], true)): ?><a class="btn ghost" href="/admin">Админка</a><?php endif; ?>
  </div>
</section>
<section class="card fade-in" id="topupCard">
  <h3>Пополнение баланса</h3>
  <p class="muted">Пополните рублёвый баланс на любую сумму. Эти рубли можно тратить на полную оплату заказа.</p>
  <div class="row" style="gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div>
      <label for="topupAmount">Сумма пополнения (₽)</label>
      <input id="topupAmount" type="number" min="1" max="50000" step="0.01" value="300">
    </div>
    <button class="btn" type="button" id="topupBtn">Пополнить через СБП</button>
  </div>
  <small class="muted" id="topupStatus">Минимальная сумма: 1 ₽.</small>
</section>
<section id="inAppFeed" class="card fade-in" hidden></section>
<section class="card fade-in">
  <a href="<?= htmlspecialchars($review2gis) ?>" target="_blank">Оставить отзыв в 2ГИС</a><br>
  <a href="<?= htmlspecialchars($reviewYandex) ?>" target="_blank">Оставить отзыв в Яндекс Картах</a><br>
  <a href="/profile/phone-change">Сменить номер</a> · <a href="/profile/birthday">Дата рождения</a>
</section>
<section class="card fade-in"><h3>История</h3>
<?php foreach($history as $row): ?><div><strong><?= htmlspecialchars($row['title']) ?></strong> · <?= htmlspecialchars((string)$row['value']) ?> · <?= htmlspecialchars((string)$row['meta']) ?> · <?= htmlspecialchars($row['created_at']) ?></div><?php endforeach; ?>
</section>

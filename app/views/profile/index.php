<h2>Личный кабинет</h2>
<section class="card widget" data-reward-available="<?= (int)$loyalty['reward_available'] ?>">
  <div class="kpi-row">
    <div><small>Баланс</small><strong><?= number_format((float)$cashback, 2, '.', ' ') ?> ₽</strong></div>
    <div><small>Штампы</small><strong><?= (int)$loyalty['stamps'] ?>/6</strong></div>
  </div>
  <div class="stamps"><?php for($i=1;$i<=6;$i++): ?><span class="dot <?= $i <= (int)$loyalty['stamps'] ? 'filled':'' ?>"></span><?php endfor; ?></div>
  <?php if ((int)$loyalty['reward_available'] === 1): ?><div class="ok">Награда доступна 🎁</div><?php endif; ?>
  <div class="row">
    <a class="btn" href="/profile/qr">Мой QR</a>
    <a class="btn ghost" href="/profile/invite">Пригласить друга</a>
  </div>
</section>
<section id="inAppFeed" class="card" hidden></section>
<section class="card">
  <a href="<?= htmlspecialchars($review2gis) ?>" target="_blank">Оставить отзыв в 2ГИС</a><br>
  <a href="<?= htmlspecialchars($reviewYandex) ?>" target="_blank">Оставить отзыв в Яндекс Картах</a><br>
  <a href="/profile/phone-change">Сменить номер</a> · <a href="/profile/birthday">Дата рождения</a>
</section>
<section class="card"><h3>История</h3>
<?php foreach($history as $row): ?>
  <div><strong><?= htmlspecialchars($row['title']) ?></strong> · <?= htmlspecialchars((string)$row['value']) ?> · <?= htmlspecialchars((string)$row['meta']) ?> · <?= htmlspecialchars($row['created_at']) ?></div>
<?php endforeach; ?>
</section>

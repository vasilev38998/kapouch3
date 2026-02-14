<h2>Пригласить друга</h2>
<section class="card">
  <p>Ваш реферальный код: <strong><?= htmlspecialchars($user['ref_code']) ?></strong></p>
  <div class="qr-wrap">
    <img class="qr-img" alt="Referral QR" src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= rawurlencode($inviteLink) ?>">
  </div>
  <label>Ссылка приглашения</label>
  <input id="inviteLink" value="<?= htmlspecialchars($inviteLink) ?>" readonly>
  <div class="row">
    <button class="btn" type="button" data-copy-target="#inviteLink">Копировать ссылку</button>
    <a class="btn ghost" href="https://wa.me/?text=<?= rawurlencode('Присоединяйся к программе лояльности: ' . $inviteLink) ?>" target="_blank">Поделиться в WhatsApp</a>
  </div>
  <p class="muted">Переход по ссылке фиксирует реферера и отправляет нового пользователя в авторизацию.</p>
</section>

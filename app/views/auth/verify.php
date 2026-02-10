<h2>Подтверждение OTP</h2>
<p>Код отправлен на <?= htmlspecialchars($phone) ?></p>
<form method="post" action="/auth/verify" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input name="otp" inputmode="numeric" maxlength="6" required>
  <button class="btn">Войти</button>
</form>

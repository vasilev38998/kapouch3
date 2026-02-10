<h2>Смена номера</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <label>Новый номер</label><input name="new_phone" required>
  <label>OTP нового номера (получите через /auth)</label><input name="otp" required>
  <button class="btn">Сохранить</button>
</form>

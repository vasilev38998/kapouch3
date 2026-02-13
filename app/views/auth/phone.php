<h2>Вход по телефону</h2>
<form method="post" action="/auth/send" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <label>Телефон</label>
  <input name="phone" class="js-phone" inputmode="tel" placeholder="+7 (___) ___-__-__" maxlength="18" required>
  <button class="btn">Получить SMS-код</button>
</form>

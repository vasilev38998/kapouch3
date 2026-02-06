<h2>Вход по телефону</h2>
<form method="post" action="/auth/send" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <label>Телефон</label>
  <input name="phone" placeholder="+7XXXXXXXXXX" required>
  <button class="btn">Получить SMS-код</button>
</form>

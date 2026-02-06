<h2>Начисление штампов по короткому коду</h2>
<div class="card">
  <p>Введите 5-значный код клиента. Код обновляется каждые 5–7 минут.</p>
  <form method="post" id="scanForm">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <input name="token" id="scanToken" required maxlength="5" inputmode="numeric" pattern="\d{5}" placeholder="Например: 48392">
    <button class="btn">Найти клиента</button>
  </form>
  <div id="scanStatus" class="muted"></div>
</div>

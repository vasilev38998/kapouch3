<h2>Скан QR</h2>
<div class="card">
  <p>Вставьте token (или используйте веб-камеру через getUserMedia — задел в JS).</p>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <textarea name="token" required></textarea>
    <button class="btn">Проверить</button>
  </form>
  <video id="scanVideo" autoplay playsinline></video>
</div>

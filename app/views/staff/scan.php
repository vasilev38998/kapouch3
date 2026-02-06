<h2>Скан QR</h2>
<div class="card">
  <p>Камера: авто-декод через BarcodeDetector (если поддерживается). Иначе вставьте token вручную.</p>
  <form method="post" id="scanForm">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <textarea name="token" id="scanToken" required></textarea>
    <button class="btn">Проверить</button>
  </form>
  <video id="scanVideo" autoplay playsinline></video>
  <div id="scanStatus" class="muted"></div>
</div>

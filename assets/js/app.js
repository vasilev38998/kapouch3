if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js'));
}

let deferredPrompt;
const installBtn = document.getElementById('installBtn');
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  if (installBtn) installBtn.hidden = false;
});
installBtn?.addEventListener('click', async () => {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  await deferredPrompt.userChoice;
  deferredPrompt = null;
  installBtn.hidden = true;
});

function showInAppFeed() {
  const widget = document.querySelector('[data-reward-available]');
  const feed = document.getElementById('inAppFeed');
  if (!widget || !feed) return;
  const messages = [];
  if (widget.getAttribute('data-reward-available') === '1') {
    messages.push('🎁 У вас доступна награда: можно списать бесплатный кофе.');
  }
  const lastSeen = localStorage.getItem('feed_last_seen');
  if (!lastSeen) {
    messages.push('📲 Добавьте приложение на домашний экран для быстрого доступа.');
  }
  if (messages.length) {
    feed.hidden = false;
    feed.innerHTML = '<h3>Уведомления</h3>' + messages.map((m) => `<div>${m}</div>`).join('');
  }
  localStorage.setItem('feed_last_seen', new Date().toISOString());
}

async function initCameraScan() {
  const video = document.getElementById('scanVideo');
  const tokenInput = document.getElementById('scanToken');
  const status = document.getElementById('scanStatus');
  if (!video || !navigator.mediaDevices) return;
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = stream;
    if (!('BarcodeDetector' in window)) {
      if (status) status.textContent = 'BarcodeDetector не поддерживается: используйте вставку токена вручную.';
      return;
    }

    const detector = new BarcodeDetector({ formats: ['qr_code'] });
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    const tick = async () => {
      if (!video.videoWidth || !video.videoHeight) return requestAnimationFrame(tick);
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0);
      const codes = await detector.detect(canvas).catch(() => []);
      if (codes.length && codes[0].rawValue) {
        tokenInput.value = codes[0].rawValue;
        if (status) status.textContent = 'QR распознан, отправляем...';
        document.getElementById('scanForm')?.submit();
        stream.getTracks().forEach((t) => t.stop());
        return;
      }
      requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
  } catch (e) {
    if (status) status.textContent = 'Камера недоступна: ' + (e?.message || e);
  }
}

showInAppFeed();
initCameraScan();

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

function initAnimations() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08 });
  document.querySelectorAll('.fade-in').forEach((el) => observer.observe(el));
}

function showInAppFeed() {
  const widget = document.querySelector('[data-reward-available]');
  const feed = document.getElementById('inAppFeed');
  if (!widget || !feed) return;
  const messages = [];
  if (widget.getAttribute('data-reward-available') === '1') messages.push('🎁 У вас доступна награда — можно списать бесплатный кофе.');
  if (!localStorage.getItem('feed_seen')) messages.push('📲 Закрепите приложение Kapouch на главном экране.');
  if (messages.length) {
    feed.hidden = false;
    feed.innerHTML = '<h3>Лента</h3>' + messages.map((m) => `<div>${m}</div>`).join('');
  }
  localStorage.setItem('feed_seen', '1');
}

function initCopyButtons() {
  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const selector = btn.getAttribute('data-copy-target');
      const target = btn.closest('.card')?.querySelector(selector) || document.querySelector(selector);
      const val = target?.value || target?.textContent || '';
      if (!val) return;
      await navigator.clipboard.writeText(val);
      const old = btn.textContent;
      btn.textContent = 'Скопировано ✅';
      setTimeout(() => (btn.textContent = old), 1500);
    });
  });
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
      if (status) status.textContent = 'Сканирование камерой недоступно: вставьте токен вручную.';
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
        status && (status.textContent = 'QR распознан, отправляем...');
        document.getElementById('scanForm')?.submit();
        stream.getTracks().forEach((t) => t.stop());
        return;
      }
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  } catch {
    status && (status.textContent = 'Камера недоступна');
  }
}

initAnimations();
showInAppFeed();
initCopyButtons();
initCameraScan();

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

function showInAppFeed(messages = []) {
  const widget = document.querySelector('[data-reward-available]');
  const feed = document.getElementById('inAppFeed');
  if (!feed) return;

  const list = [...messages];
  if (widget && widget.getAttribute('data-reward-available') === '1') list.push('🎁 У вас доступна награда — можно списать бесплатный кофе.');
  if (!localStorage.getItem('feed_seen')) list.push('📲 Закрепите приложение Kapouch на главном экране.');

  if (list.length) {
    feed.hidden = false;
    const uniq = Array.from(new Set(list)).slice(0, 8);
    const markAll = '<button id="feedMarkRead" class="btn ghost" style="margin:8px 0">Отметить уведомления прочитанными</button>';
    feed.innerHTML = '<h3>Лента</h3>' + markAll + uniq.map((m) => `<div>${m}</div>`).join('');
    const btn = document.getElementById('feedMarkRead');
    btn?.addEventListener('click', () => {
      fetch('/api/notifications/read-all', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _csrf: window.CSRF_TOKEN }).toString()
      }).then(() => {
        btn.textContent = 'Готово ✅';
        btn.disabled = true;
      }).catch(() => {});
    });
  }
  localStorage.setItem('feed_seen', '1');
  feed.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-notif-id]');
    if (!a) return;
    fetch('/api/notifications/click', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _csrf: window.CSRF_TOKEN, id: a.getAttribute('data-notif-id') || '' }).toString()
    }).catch(() => {});
  });
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

function initMenuFavorites() {
  const cards = Array.from(document.querySelectorAll('[data-menu-item]'));
  if (!cards.length) return;

  const toggle = document.getElementById('favoritesToggle');
  const summary = document.getElementById('favoritesSummary');
  const storageKey = 'menu_favorites';
  const stored = JSON.parse(localStorage.getItem(storageKey) || '[]');
  const favorites = new Set(stored.map(String));
  let syncing = false;

  const persistLocal = () => {
    localStorage.setItem(storageKey, JSON.stringify(Array.from(favorites)));
  };

  const syncFromServer = async () => {
    try {
      const res = await fetch('/api/menu/favorites', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data?.ok || !Array.isArray(data.ids)) return;
      favorites.clear();
      data.ids.forEach((id) => favorites.add(String(id)));
      persistLocal();
      cards.forEach(syncCard);
      updateSummary();
      applyFilter();
    } catch {}
  };

  const syncToServer = async (id) => {
    try {
      const body = new URLSearchParams({ _csrf: window.CSRF_TOKEN, menu_item_id: String(id) }).toString();
      const res = await fetch('/api/menu/favorites/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data?.ok) return;
      const active = !!data.active;
      if (active) favorites.add(String(id)); else favorites.delete(String(id));
      persistLocal();
      cards.forEach(syncCard);
      updateSummary();
      applyFilter();
    } catch {}
  };

  const updateSummary = () => {
    if (!summary) return;
    const count = favorites.size;
    const suffix = syncing ? ' · синхронизация…' : '';
    summary.textContent = count
      ? `В избранном: ${count}. Нажмите ❤, чтобы быстро убрать или добавить позицию.${suffix}`
      : `Добавляйте любимые позиции в избранное, чтобы не искать их каждый раз.${suffix}`;
  };

  const syncCard = (card) => {
    const id = String(card.dataset.menuId || '');
    const btn = card.querySelector('[data-favorite-toggle]');
    const isFav = favorites.has(id);
    card.classList.toggle('is-favorite', isFav);
    if (btn) {
      btn.classList.toggle('is-active', isFav);
      btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
      const label = btn.querySelector('.favorite-text');
      if (label) label.textContent = isFav ? 'В избранном' : 'В избранное';
    }
  };

  const applyFilter = () => {
    if (!toggle) return;
    const showFavs = toggle.checked;
    cards.forEach((card) => {
      const id = String(card.dataset.menuId || '');
      const isFav = favorites.has(id);
      card.style.display = showFavs && !isFav ? 'none' : '';
    });
  };

  cards.forEach((card) => {
    syncCard(card);
    const btn = card.querySelector('[data-favorite-toggle]');
    btn?.addEventListener('click', async () => {
      const id = String(card.dataset.menuId || '');
      if (!id) return;
      if (favorites.has(id)) favorites.delete(id); else favorites.add(id);
      persistLocal();
      syncCard(card);
      updateSummary();
      applyFilter();
      syncing = true;
      updateSummary();
      await syncToServer(id);
      syncing = false;
      updateSummary();
    });
  });

  toggle?.addEventListener('change', applyFilter);
  updateSummary();
  applyFilter();
  syncFromServer();
}



function initAqsiSync() {
  const btn = document.querySelector('[data-aqsi-sync]');
  const ext = document.getElementById('aqsiExternalId');
  const amount = document.querySelector('input[name="total_amount"]');
  const status = document.getElementById('aqsiStatus');
  const sourceInput = document.getElementById('aqsiSource');
  if (!btn || !ext || !amount) return;

  btn.addEventListener('click', async () => {
    const externalId = (ext.value || '').trim();
    if (!externalId) {
      if (status) status.textContent = 'Укажите ID чека AQSI.';
      return;
    }

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Загрузка...';
    if (status) status.textContent = 'Запрашиваем данные чека из AQSI...';

    try {
      const url = `/api/staff/aqsi/check?external_id=${encodeURIComponent(externalId)}&_csrf=${encodeURIComponent(window.CSRF_TOKEN || '')}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data?.ok) {
        if (status) status.textContent = 'Чек не найден в AQSI или нет доступа к API.';
        return;
      }

      amount.value = String(data.total_amount || '');
      if (sourceInput) sourceInput.value = String(data.source || '');
      if (status) {
        const paid = data.paid_at ? `, оплачен: ${data.paid_at}` : '';
        const sourceLabel = data.source === 'receipt' ? 'чек' : (data.source === 'order' ? 'заказ' : 'документ');
        status.textContent = `Успешно (${sourceLabel}): сумма ${data.total_amount} ₽${paid}`;
      }
    } catch {
      if (status) status.textContent = 'Ошибка связи с AQSI. Проверьте настройки API.';
    } finally {
      btn.disabled = false;
      btn.textContent = oldText;
    }
  });
}


function initMenuCart() {
  const cards = Array.from(document.querySelectorAll('[data-menu-item]'));
  const cartRoot = document.querySelector('[data-menu-cart]');
  if (!cards.length || !cartRoot) return;

  const list = document.getElementById('menuCartList');
  const totalEl = document.getElementById('menuCartTotal');
  const payBtn = document.getElementById('menuPayBtn');
  const clearBtn = document.getElementById('menuCartClear');
  const status = document.getElementById('menuPayStatus');
  const storageKey = 'menu_cart_v1';
  const cart = new Map(Object.entries(JSON.parse(localStorage.getItem(storageKey) || '{}')).map(([k,v]) => [k, Number(v) || 0]));

  const save = () => {
    const obj = {};
    cart.forEach((qty, id) => { if (qty > 0) obj[id] = qty; });
    localStorage.setItem(storageKey, JSON.stringify(obj));
  };

  const render = () => {
    let total = 0;
    const rows = [];
    cards.forEach((card) => {
      const id = String(card.dataset.menuId || '');
      const name = String(card.dataset.menuName || '');
      const price = Number(card.dataset.menuPrice || '0');
      const qty = Math.max(0, Math.min(20, Number(cart.get(id) || 0)));
      cart.set(id, qty);
      const input = card.querySelector('[data-qty-input]');
      if (input) input.value = String(qty);
      if (qty > 0 && price > 0) {
        const sum = qty * price;
        total += sum;
        rows.push(`<div class="menu-cart-line"><span>${name} × ${qty}</span><strong>${sum.toFixed(2)} ₽</strong></div>`);
      }
    });

    if (list) list.innerHTML = rows.length ? rows.join('') : '<span class="muted">Добавьте позиции из меню.</span>';
    if (totalEl) totalEl.textContent = `${total.toFixed(2)} ₽`;
    if (payBtn) payBtn.disabled = total <= 0;
    save();
  };

  cards.forEach((card) => {
    const id = String(card.dataset.menuId || '');
    const input = card.querySelector('[data-qty-input]');
    const plus = card.querySelector('[data-qty-plus]');
    const minus = card.querySelector('[data-qty-minus]');

    plus?.addEventListener('click', () => {
      cart.set(id, Math.min(20, Number(cart.get(id) || 0) + 1));
      render();
    });
    minus?.addEventListener('click', () => {
      cart.set(id, Math.max(0, Number(cart.get(id) || 0) - 1));
      render();
    });
    input?.addEventListener('change', () => {
      cart.set(id, Math.max(0, Math.min(20, Number(input.value || 0))));
      render();
    });
  });

  clearBtn?.addEventListener('click', () => {
    cart.clear();
    render();
  });

  payBtn?.addEventListener('click', async () => {
    const items = [];
    cart.forEach((qty, id) => {
      if (qty > 0) items.push({ id: Number(id), qty: Number(qty) });
    });
    if (!items.length) return;

    payBtn.disabled = true;
    const oldText = payBtn.textContent;
    payBtn.textContent = 'Создаём платёж...';
    if (status) status.textContent = 'Инициализация платежа...';

    try {
      const body = new URLSearchParams({ _csrf: window.CSRF_TOKEN, items: JSON.stringify(items) }).toString();
      const res = await fetch('/api/checkout/sbp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body,
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data?.ok || !data?.payment_url) {
        if (res.status === 401) {
          if (status) status.textContent = 'Для оплаты войдите в аккаунт.';
        } else {
          if (status) status.textContent = 'Не удалось создать платёж. Попробуйте ещё раз.';
        }
        return;
      }
      if (status) status.textContent = `Переходим к оплате: ${data.amount} ₽`;
      window.location.href = data.payment_url;
    } catch {
      if (status) status.textContent = 'Ошибка сети при создании платежа.';
    } finally {
      payBtn.disabled = false;
      payBtn.textContent = oldText;
    }
  });

  render();
}

function applyPhoneMask(value) {
  const d = value.replace(/\D/g, '').replace(/^8/, '7');
  const n = d.startsWith('7') ? d.slice(1, 11) : d.slice(0, 10);
  const p1 = n.slice(0, 3);
  const p2 = n.slice(3, 6);
  const p3 = n.slice(6, 8);
  const p4 = n.slice(8, 10);
  let out = '+7';
  if (p1) out += ` (${p1}`;
  if (p1.length === 3) out += ')';
  if (p2) out += ` ${p2}`;
  if (p3) out += `-${p3}`;
  if (p4) out += `-${p4}`;
  return out;
}

function initPhoneMask() {
  document.querySelectorAll('.js-phone').forEach((input) => {
    input.addEventListener('input', () => {
      input.value = applyPhoneMask(input.value);
    });
    if (input.value) input.value = applyPhoneMask(input.value);
  });
}

async function initPushNotifications() {
  const authPage = window.location.pathname.startsWith('/auth');
  if (authPage || !('Notification' in window)) return;

  if (Notification.permission === 'default') {
    try { await Notification.requestPermission(); } catch {}
  }

    const publicKey = window.WEB_PUSH_PUBLIC_KEY || '';
  const hasPushManager = ('serviceWorker' in navigator) && ('PushManager' in window) && publicKey;

  const subscribeFallback = async () => {
    let deviceId = localStorage.getItem('push_device_id');
    if (!deviceId) {
      deviceId = (crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`);
      localStorage.setItem('push_device_id', deviceId);
    }
    await fetch('/api/push/subscribe', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _csrf: window.CSRF_TOKEN, endpoint: `fallback-${deviceId}`, permission: Notification.permission }).toString()
    });
  };

  const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  };

  try {
    if (hasPushManager) {
      const reg = await navigator.serviceWorker.ready;
      let sub = await reg.pushManager.getSubscription();
      if (!sub && Notification.permission === 'granted') {
        sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(publicKey)
        });
      }
      if (sub) {
        const json = sub.toJSON();
        await fetch('/api/push/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ _csrf: window.CSRF_TOKEN, permission: Notification.permission, ...json })
        });
      } else {
        await subscribeFallback();
      }
    } else {
      await subscribeFallback();
    }
  } catch {
    try { await subscribeFallback(); } catch {}
  }

  const delivered = new Set(JSON.parse(localStorage.getItem('notified_ids') || '[]'));
  const poll = async () => {
    try {
      const res = await fetch('/api/notifications/poll', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      const feedMessages = [];
      for (const item of (data.items || [])) {
        const msg = item.url ? `🔔 <a data-notif-id="${item.id}" href="${item.url}">${item.title}</a>: ${item.body}` : `🔔 ${item.title}: ${item.body}`;
        feedMessages.push(msg);
        if (!delivered.has(item.id) && Notification.permission === 'granted') {
          const note = new Notification(item.title, { body: item.body });
          if (item.url) {
            note.onclick = () => {
              window.focus();
              fetch('/api/notifications/click', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ _csrf: window.CSRF_TOKEN, id: String(item.id) }).toString()
              }).catch(() => {});
              window.location.href = item.url;
            };
          }
          delivered.add(item.id);
        }
      }
      localStorage.setItem('notified_ids', JSON.stringify(Array.from(delivered).slice(-200)));
      if (feedMessages.length) showInAppFeed(feedMessages.slice(0, 5));
    } catch {}
  };
  poll();
  setInterval(poll, 30000);
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
      if (status) status.textContent = 'Сканирование камерой недоступно: введите короткий код вручную.';
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
initMenuFavorites();
initMenuCart();
initAqsiSync();
initPhoneMask();
initPushNotifications();
initCameraScan();

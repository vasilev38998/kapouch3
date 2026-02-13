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

function initBottomNavActive() {
  const path = window.location.pathname || '/';
  document.querySelectorAll('.bottom-nav a').forEach((a) => {
    const href = a.getAttribute('href') || '';
    const active = href !== '/' && (path === href || path.startsWith(href + '/'));
    a.classList.toggle('is-active', active);
  });
}



function triggerHaptic(pattern = 8) {
  try { if (navigator.vibrate) navigator.vibrate(pattern); } catch {}
}

function showInAppFeed(items = []) {
  const widget = document.querySelector('[data-reward-available]');
  const feed = document.getElementById('inAppFeed');
  if (!feed) return;

  const notifications = Array.isArray(items) ? items.filter(Boolean).slice(0, 12) : [];
  if (widget && widget.getAttribute('data-reward-available') === '1') {
    notifications.unshift({ title: 'Награда доступна', body: 'Можно списать бесплатный кофе 🎁', system: true });
  }

  if (!notifications.length) {
    feed.hidden = true;
    feed.innerHTML = '';
    return;
  }

  feed.hidden = false;
  const cards = notifications.map((item) => {
    const id = item.id ? String(item.id) : '';
    const title = item.title || 'Уведомление';
    const body = item.body || '';
    const image = item.image || '';
    const ts = item.created_at || '';
    const readButton = item.system ? '' : `<button class="btn ghost" data-mark-read="${id}">Прочитано</button>`;
    const imageHtml = image ? `<img class="notif-image" src="${image}" alt="notification">` : '';
    return `<article class="notif-card ${item.system ? 'notif-system' : ''}" data-notif-id="${id}">
      ${imageHtml}
      <div class="notif-content">
        <strong>${title}</strong>
        <p>${body}</p>
        <div class="row" style="justify-content:space-between;align-items:center">
          <small class="muted">${ts}</small>
          ${readButton}
        </div>
      </div>
    </article>`;
  }).join('');

  feed.innerHTML = `<h3>Уведомления</h3><div class="notif-grid">${cards}</div>`;
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


function initEngagementFeatures() {
  const streakEl = document.getElementById('streakValue');
  const bonusEl = document.getElementById('dailyBonusValue');
  const bonusBtn = document.getElementById('dailyBonusBtn');
  const hint = document.getElementById('engagementHint');
  const themeBtn = document.getElementById('themeToggleBtn');

  const today = new Date().toISOString().slice(0, 10);
  const streakKey = 'engagement_streak_v1';
  const bonusKey = 'daily_bonus_claim_v1';
  const streakData = JSON.parse(localStorage.getItem(streakKey) || '{}');

  const prev = streakData.last_date || '';
  let streak = Number(streakData.count || 0);
  const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
  if (prev !== today) {
    if (prev === yesterday) streak += 1;
    else streak = 1;
    localStorage.setItem(streakKey, JSON.stringify({ last_date: today, count: streak }));
  }
  if (streakEl) streakEl.textContent = String(streak);

  const bonusData = JSON.parse(localStorage.getItem(bonusKey) || '{}');
  const bonuses = ['+5% кэшбэка на день', 'Бесплатный сироп', 'Секретная позиция -10%', 'Двойные штампы'];
  const claimedToday = bonusData.date === today;
  if (bonusEl) bonusEl.textContent = claimedToday ? (bonusData.bonus || 'получен') : 'не открыт';
  bonusBtn?.addEventListener('click', () => {
    if (claimedToday) {
      if (hint) hint.textContent = `Сегодня бонус уже получен: ${bonusData.bonus}`;
      return;
    }
    const bonus = bonuses[Math.floor(Math.random() * bonuses.length)];
    localStorage.setItem(bonusKey, JSON.stringify({ date: today, bonus }));
    if (bonusEl) bonusEl.textContent = bonus;
    if (hint) hint.textContent = `Бонус дня: ${bonus}. Покажите бариста экран профиля.`;
    triggerHaptic([20, 30, 20]);
  });

  const themeKey = 'kapouch_theme';
  const applyTheme = () => {
    const t = localStorage.getItem(themeKey) || 'light';
    document.documentElement.classList.toggle('theme-dark', t === 'dark');
  };
  applyTheme();
  themeBtn?.addEventListener('click', () => {
    const curr = localStorage.getItem(themeKey) || 'light';
    localStorage.setItem(themeKey, curr === 'light' ? 'dark' : 'light');
    applyTheme();
  });
}

function initLuckyAndRecentMenu() {
  const cards = Array.from(document.querySelectorAll('[data-menu-item]'));
  if (!cards.length) return;
  const luckyEl = document.getElementById('luckyPickHint');
  const recentEl = document.getElementById('recentMenuView');
  const luckyIdx = Math.floor((Date.now() / 86400000)) % cards.length;
  const luckyCard = cards[luckyIdx];
  if (luckyCard) {
    luckyCard.classList.add('lucky-pick');
    if (luckyEl) luckyEl.textContent = `🎯 Лаки-позиция дня: ${luckyCard.dataset.menuName || 'выбрано'}`;
  }
  const recentKey = 'recent_menu_items_v1';
  const saveRecent = (id, name) => {
    const arr = JSON.parse(localStorage.getItem(recentKey) || '[]').filter((r) => r.id !== id);
    arr.unshift({ id, name });
    localStorage.setItem(recentKey, JSON.stringify(arr.slice(0, 5)));
    renderRecent();
  };
  const renderRecent = () => {
    if (!recentEl) return;
    const arr = JSON.parse(localStorage.getItem(recentKey) || '[]');
    if (!arr.length) {
      recentEl.textContent = 'Вы еще не просматривали позиции — выберите что-нибудь интересное.';
      return;
    }
    recentEl.innerHTML = 'Недавно смотрели: ' + arr.map((x) => `<span class="chip">${x.name}</span>`).join(' ');
  };
  cards.forEach((card) => {
    card.addEventListener('click', () => {
      saveRecent(String(card.dataset.menuId || ''), card.dataset.menuName || 'Позиция');
    });
  });
  renderRecent();
}

function initMenuSearch() {
  const input = document.getElementById('menuSearch');
  const minPrice = document.getElementById('menuMinPrice');
  const maxPrice = document.getElementById('menuMaxPrice');
  const cards = Array.from(document.querySelectorAll('[data-menu-item]'));
  if (!cards.length || (!input && !minPrice && !maxPrice)) return;

  let t;
  const apply = () => {
    const q = ((input?.value) || '').trim().toLowerCase();
    const min = Number(minPrice?.value || 0);
    const max = Number(maxPrice?.value || 0);
    cards.forEach((card) => {
      const text = [
        card.dataset.menuName || '',
        card.dataset.menuCategory || '',
        card.dataset.menuDescription || ''
      ].join(' ').toLowerCase();
      const price = Number(card.dataset.menuPrice || 0);
      const byText = q === '' || text.includes(q);
      const byMin = !min || price >= min;
      const byMax = !max || price <= max;
      card.classList.toggle('is-search-hidden', !(byText && byMin && byMax));
    });
  };

  const onInput = () => { clearTimeout(t); t = setTimeout(apply, 120); };
  input?.addEventListener('input', onInput);
  minPrice?.addEventListener('input', onInput);
  maxPrice?.addEventListener('input', onInput);
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
  const spendInput = document.getElementById('menuCashbackSpend');
  const spendHint = document.getElementById('menuCashbackHint');
  const etaHint = document.getElementById('menuEtaHint');
  const shareBtn = document.getElementById('menuCartShare');
  const upsell = document.getElementById('menuUpsell');
  const storageKey = 'menu_cart_v1';
  const lastOrderKey = 'menu_last_paid_order_v1';
  const restoreBtn = document.getElementById('menuRestoreLast');
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

    const spend = Math.max(0, Number(spendInput?.value || 0));
    const payable = Math.max(0.01, total - spend);
    if (spendHint) spendHint.textContent = `К оплате по СБП: ${payable.toFixed(2)} ₽`;

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
      triggerHaptic(6);
      render();
    });
    minus?.addEventListener('click', () => {
      cart.set(id, Math.max(0, Number(cart.get(id) || 0) - 1));
      triggerHaptic(6);
      render();
    });
    input?.addEventListener('change', () => {
      cart.set(id, Math.max(0, Math.min(20, Number(input.value || 0))));
      render();
    });
  });


  restoreBtn?.addEventListener('click', () => {
    const saved = JSON.parse(localStorage.getItem(lastOrderKey) || '{}');
    if (!saved || typeof saved !== 'object' || !saved.items) {
      if (status) status.textContent = 'Нет предыдущего заказа для повторения.';
      return;
    }
    Object.entries(saved.items).forEach(([id, qty]) => {
      cart.set(String(id), Math.max(0, Math.min(20, Number(qty) || 0)));
    });
    if (spendInput && typeof saved.cashback_spend !== 'undefined') {
      spendInput.value = String(saved.cashback_spend);
    }
    if (status) status.textContent = 'Прошлая корзина восстановлена.';
    render();
  });

  shareBtn?.addEventListener('click', async () => {
    const lines = [];
    cart.forEach((qty, id) => {
      if (qty <= 0) return;
      const card = cards.find((c) => String(c.dataset.menuId || '') === String(id));
      const name = card?.dataset.menuName || `#${id}`;
      lines.push(`${name} × ${qty}`);
    });
    if (!lines.length) {
      if (status) status.textContent = 'Корзина пуста — пока нечем делиться.';
      return;
    }
    const text = `Мой заказ в Kapouch:\n${lines.join('\n')}`;
    try {
      await navigator.clipboard.writeText(text);
      if (status) status.textContent = 'Состав корзины скопирован — можно отправить другу.';
    } catch {
      if (status) status.textContent = 'Не удалось скопировать корзину.';
    }
  });
  clearBtn?.addEventListener('click', () => {
    cart.clear();
    render();
  });

  spendInput?.addEventListener('input', render);

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
      const cashbackSpend = Math.max(0, Number(spendInput?.value || 0)).toFixed(2);
      const body = new URLSearchParams({ _csrf: window.CSRF_TOKEN, items: JSON.stringify(items), cashback_spend: cashbackSpend }).toString();
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
        } else if (data?.error === 'config_missing') {
          if (status) status.textContent = 'Платёж временно недоступен: не настроены ключи Т‑Банка.';
        } else if (data?.error === 'provider_error') {
          if (status) status.textContent = data?.message ? `Т‑Банк: ${data.message}` : 'Т‑Банк отклонил инициализацию платежа.';
        } else {
          if (status) status.textContent = 'Не удалось создать платёж. Проверьте настройки и повторите.';
        }
        return;
      }
      if (status) status.textContent = `К оплате: ${data.amount} ₽ (списано кэшбэка: ${data.cashback_spend || 0} ₽)`;
      const snapshot = {};
      cart.forEach((qty, id) => { if (qty > 0) snapshot[id] = qty; });
      localStorage.setItem(lastOrderKey, JSON.stringify({ items: snapshot, cashback_spend: Number(spendInput?.value || 0), saved_at: new Date().toISOString() }));
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



function initStaffLiveOrders() {
  const feed = document.getElementById('liveOrdersFeed');
  const status = document.getElementById('liveOrdersStatus');
  if (!feed || !status) return;

  let knownIds = new Set();

  const beep = () => {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 860;
      g.gain.value = 0.02;
      o.connect(g); g.connect(ctx.destination);
      o.start();
      setTimeout(() => { o.stop(); ctx.close(); }, 180);
    } catch {}
  };

  const render = (items = []) => {
    if (!items.length) {
      feed.innerHTML = '<div class="card muted">Пока нет заказов.</div>';
      return;
    }

    feed.innerHTML = items.map((item) => {
      const cart = Array.isArray(item.cart) ? item.cart : [];
      const lines = cart.map((line) => `<div class="live-order-line"><span>${line.name} × ${line.qty}</span><strong>${Number(line.sum || 0).toFixed(2)} ₽</strong></div>`).join('');
      return `<article class="card live-order" data-live-id="${item.id}">
        <div class="row" style="justify-content:space-between;align-items:center">
          <strong>Заказ ${item.external_order_id}</strong>
          <span class="chip">${item.status}</span>
        </div>
        <div class="muted">Телефон: ${item.phone} · ${item.created_at}</div>
        <div class="live-order-lines">${lines || '<div class="muted">Состав не передан</div>'}</div>
        <div class="row" style="justify-content:space-between;align-items:center">
          <strong>${Number(item.amount || 0).toFixed(2)} ₽</strong>
          <select data-live-status>
            ${['created','accepted','preparing','ready','done','cancelled'].map((s) => `<option value="${s}" ${s===item.status?'selected':''}>${s}</option>`).join('')}
          </select>
        </div>
      </article>`;
    }).join('');
  };

  const load = async () => {
    try {
      const res = await fetch('/api/staff/orders/live?limit=40', { credentials: 'same-origin' });
      if (!res.ok) {
        status.textContent = 'Ошибка доступа';
        return;
      }
      const data = await res.json();
      if (!data?.ok) {
        status.textContent = 'Ошибка данных';
        return;
      }
      const items = data.items || [];
      const nextIds = new Set(items.map((item) => String(item.id)));
      const hasNew = Array.from(nextIds).some((id) => !knownIds.has(id));
      if (hasNew && knownIds.size > 0) beep();
      knownIds = nextIds;
      status.textContent = `Онлайн · ${new Date().toLocaleTimeString()}`;
      render(items);
    } catch {
      status.textContent = 'Нет соединения';
    }
  };

  feed.addEventListener('change', async (e) => {
    const select = e.target.closest('[data-live-status]');
    if (!select) return;
    const card = select.closest('[data-live-id]');
    const id = card?.getAttribute('data-live-id') || '';
    if (!id) return;
    try {
      await fetch('/api/staff/orders/live/status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: new URLSearchParams({ _csrf: window.CSRF_TOKEN, id, status: select.value }).toString(),
      });
      load();
    } catch {}
  });

  load();
  setInterval(load, 4000);
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

async function initProfileNotifications() {
  const feed = document.getElementById('inAppFeed');
  if (!feed) return;

  const markRead = async (id) => {
    if (!id) return;
    try {
      await fetch('/api/notifications/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _csrf: window.CSRF_TOKEN, id: String(id) }).toString(),
      });
    } catch {}
  };

  feed.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-mark-read]');
    if (!btn) return;
    await markRead(btn.getAttribute('data-mark-read'));
    btn.textContent = 'Прочитано ✅';
    btn.disabled = true;
  });

  const load = async () => {
    try {
      const res = await fetch('/api/notifications/poll', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      const items = (data.items || []).map((item) => ({
        id: item.id,
        title: item.title,
        body: item.body,
        image: item.url || '',
        created_at: item.created_at || '',
      }));
      showInAppFeed(items);
    } catch {}
  };

  await load();
  setInterval(load, 20000);
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
initBottomNavActive();
showInAppFeed();
initCopyButtons();
initMenuFavorites();
initEngagementFeatures();
initLuckyAndRecentMenu();
initMenuSearch();
initMenuCart();
initAqsiSync();
initStaffLiveOrders();
initPhoneMask();
initProfileNotifications();
initCameraScan();

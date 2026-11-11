// glowth storefront. products, accounts, coupons and orders come from /api

const $ = s => document.querySelector(s);
const $$ = s => [...document.querySelectorAll(s)];
const peso = n => '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: 2 });
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const stars = r => '★'.repeat(Math.round(r)) + '☆'.repeat(5 - Math.round(r));

const state = {
    products: [],
    user: null,
    csrf: '',
    coupons: [],
    favorites: [],
    settings: { shippingFee: 99, freeShippingMin: 1500, welcomePercent: 15 },
    couponCode: '',
    afterLogin: null,
};

// api

async function api(path, body) {
    const options = { credentials: 'same-origin', headers: {} };
    if (body !== undefined) {
        options.method = 'POST';
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-Token'] = state.csrf;
        options.body = JSON.stringify(body);
    }
    let res;
    try {
        res = await fetch('api/' + path, options);
    } catch (err) {
        throw new Error('cannot reach the server. check your connection and try again.');
    }
    let data = {};
    try { data = await res.json(); } catch (err) { /* empty body */ }
    if (!res.ok) {
        const error = new Error(data.error || 'something went wrong. please try again.');
        error.status = res.status;
        throw error;
    }
    return data;
}

function applyMe(data) {
    state.user = data.user;
    state.csrf = data.csrf || state.csrf;
    state.coupons = data.coupons || [];
    state.favorites = data.favorites || [];
    $('#openAccount').classList.toggle('in', !!state.user);
    $('#welcomeBanner').style.display = state.user ? 'none' : '';
    refreshFavUI();
}

// cart (kept in the browser, priced by the server at checkout)

function readCart() {
    try { return JSON.parse(localStorage.getItem('glowth_cart')) || []; } catch (err) { return []; }
}
function saveCart(cart) {
    try { localStorage.setItem('glowth_cart', JSON.stringify(cart)); } catch (err) { /* storage blocked */ }
}
const getProduct = id => state.products.find(p => p.id === id);

function cartLines() {
    return readCart().map(i => ({ ...i, product: getProduct(i.productId) })).filter(l => l.product);
}
function addToCart(id, qty = 1) {
    const cart = readCart();
    const item = cart.find(i => i.productId === id);
    const max = getProduct(id).stock;
    if (item) item.quantity = Math.min(item.quantity + qty, max, 99);
    else cart.push({ productId: id, quantity: Math.min(qty, max, 99) });
    saveCart(cart);
}
function setQuantity(id, qty) {
    if (qty < 1) return saveCart(readCart().filter(i => i.productId !== id));
    const cart = readCart();
    const item = cart.find(i => i.productId === id);
    if (item) { item.quantity = Math.min(qty, getProduct(id).stock, 99); saveCart(cart); }
}

// totals come from the server when a coupon is applied, so the numbers always match the order
async function getTotals() {
    const lines = cartLines();
    const subtotal = lines.reduce((s, l) => s + l.product.price * l.quantity, 0);
    const local = (discount = 0) => {
        const after = subtotal - discount;
        const shipping = !subtotal || after >= state.settings.freeShippingMin ? 0 : state.settings.shippingFee;
        return { subtotal, discount, shipping, total: after + shipping, coupon: null, coupon_error: null };
    };
    if (!lines.length || !state.couponCode) return local();
    try {
        const q = await api('checkout.php', { action: 'quote', items: lines.map(l => ({ id: l.productId, qty: l.quantity })), coupon: state.couponCode });
        if (q.error) return local();
        if (q.coupon_error) { return { ...local(), coupon_error: q.coupon_error }; }
        return q;
    } catch (err) {
        return local();
    }
}

function imgTag(p) {
    return `<img src="${esc(p.image)}" alt="${esc(p.name)}" loading="lazy" onerror="this.outerHTML='<div class=&quot;img-fallback&quot;>${esc(p.name.charAt(0))}</div>'">`;
}

let toastTimer;
function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
}

// panels

function closeAll() {
    $$('.drawer.open, .modal.open').forEach(el => el.classList.remove('open'));
    $('#overlay').classList.remove('show');
    document.body.classList.remove('lock');
}
function openPanel(el) {
    closeAll();
    el.classList.add('open');
    $('#overlay').classList.add('show');
    document.body.classList.add('lock');
}
$('#overlay').addEventListener('click', closeAll);
document.addEventListener('click', e => {
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) closeAll();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

// shop grid

const SUBS = { Makeup: ['Face', 'Lips', 'Eyes'], Skincare: ['Cleanse', 'Treat', 'Protect'] };
const SUB_LABEL = { Face: 'Face', Lips: 'Lips', Eyes: 'Eyes', Cleanse: 'Cleanse & Tone', Treat: 'Treat & Repair', Protect: 'Hydrate & Protect' };
const CAT_COPY = { Makeup: 'Face, lips and eyes in soft, wearable shades.', Skincare: 'Cleanse, treat and protect, every day.' };
const COL_COPY = {
    all: ['Shop All', 'Makeup and skincare, made for everyday radiance.'],
    new: ['New Arrivals', 'The latest additions to the GLOWTH collection.'],
    best: ['Best Sellers', 'The products our customers come back for.'],
    sale: ['Sale', 'Up to 40% off selected favorites.'],
};
let shop = { col: 'all', cat: 'All', sub: 'All', sort: 'featured' };
let query = '';
let currentView = 'home';

function filteredProducts() {
    const q = query.trim().toLowerCase();
    const list = state.products.filter(p =>
        (shop.cat === 'All' || p.category === shop.cat) &&
        (shop.sub === 'All' || p.sub === shop.sub) &&
        (shop.col === 'all' || (shop.col === 'new' && p.tag === 'New') || (shop.col === 'best' && p.tag === 'Bestseller') || (shop.col === 'sale' && p.oldPrice)) &&
        (!q || (p.name + ' ' + p.description + ' ' + p.category + ' ' + p.sub).toLowerCase().includes(q))
    );
    if (shop.sort === 'low') list.sort((a, b) => a.price - b.price);
    if (shop.sort === 'high') list.sort((a, b) => b.price - a.price);
    if (shop.sort === 'rating') list.sort((a, b) => b.rating - a.rating);
    return list;
}

const priceHTML = p => p.oldPrice ? `${peso(p.price)}<s class="old">${peso(p.oldPrice)}</s>` : peso(p.price);
const percentOff = p => Math.round((1 - p.price / p.oldPrice) * 100);
const isFav = id => state.favorites.includes(id);

function renderProducts() {
    const list = filteredProducts();
    $('#resultCount').textContent = list.length + (list.length === 1 ? ' product' : ' products');
    $('#products').innerHTML = list.length ? list.map(p => `
        <article class="card">
            <div class="card-img" data-view="${p.id}">
                ${imgTag(p)}
                ${p.stock < 1 ? '<span class="tag">Sold out</span>' : p.oldPrice ? `<span class="tag sale">-${percentOff(p)}%</span>` : p.tag ? `<span class="tag">${esc(p.tag)}</span>` : ''}
                <button class="fav ${isFav(p.id) ? 'on' : ''}" data-fav="${p.id}" aria-label="Toggle favorite">${isFav(p.id) ? '♥' : '♡'}</button>
            </div>
            <div class="card-body">
                <span class="card-cat">${p.category} · ${SUB_LABEL[p.sub] || esc(p.sub)}</span>
                <h3 class="card-name" data-view="${p.id}">${esc(p.name)}</h3>
                <div class="rating"><span class="stars">${stars(p.rating)}</span> ${p.rating.toFixed(1)}</div>
                <div class="card-foot">
                    <span class="price">${priceHTML(p)}</span>
                    <button class="btn btn-sm" data-add="${p.id}" ${p.stock < 1 ? 'disabled' : ''}>Add to Bag</button>
                </div>
            </div>
        </article>`).join('')
        : '<div class="empty-state"><h3>No products found</h3><p>Try a different search or category.</p></div>';
}

function renderShopHead() {
    let title, subtitle;
    const q = query.trim();
    if (q) {
        title = 'Results for “' + q + '”';
        subtitle = '';
    } else {
        title = shop.sub !== 'All' ? SUB_LABEL[shop.sub] : shop.cat !== 'All' ? shop.cat : COL_COPY[shop.col][0];
        if (shop.col !== 'all' && shop.cat !== 'All') title = COL_COPY[shop.col][0] + ': ' + title;
        subtitle = shop.cat !== 'All' ? (shop.col !== 'all' ? COL_COPY[shop.col][1] : CAT_COPY[shop.cat]) : COL_COPY[shop.col][1];
    }
    $('#shopTitle').textContent = title;
    $('#shopSub').textContent = subtitle;
}

function renderSubChips() {
    const subs = SUBS[shop.cat] || [];
    $('#subFilters').innerHTML = subs.length
        ? ['All', ...subs].map(x => `<button class="chip sm ${shop.sub === x ? 'active' : ''}" data-sub="${x}">${x === 'All' ? 'All ' + shop.cat : SUB_LABEL[x]}</button>`).join('')
        : '';
}

function shopHash() {
    return '#' + [shop.col === 'all' ? 'shop' : shop.col, shop.cat !== 'All' ? shop.cat.toLowerCase() : null, shop.sub !== 'All' ? shop.sub.toLowerCase() : null].filter(Boolean).join('/');
}

function refreshShop() {
    $$('#filters .chip').forEach(c => c.classList.toggle('active', c.dataset.cat === shop.cat));
    $('#sortSelect').value = shop.sort;
    renderShopHead();
    renderSubChips();
    renderProducts();
    updateNav();
}

function setShop(next) {
    shop = { ...shop, ...next };
    refreshShop();
    if (currentView === 'shop') history.replaceState(null, '', shopHash());
}

function updateNav() {
    const key = currentView === 'shop' ? (shop.col === 'all' ? 'shop' : shop.col) : currentView;
    $$('#navLinks > li > a').forEach(a => a.classList.toggle('active', a.dataset.key === key));
}

function showView(view) {
    currentView = view;
    $$('[data-views]').forEach(el => { el.hidden = el.dataset.views !== view; });
    window.scrollTo(0, 0);
    updateNav();
}

function routeFromHash() {
    const parts = location.hash.slice(1).toLowerCase().split('/');
    const v = parts[0];
    if (v === 'about') return showView('about');
    if (['shop', 'new', 'best', 'sale'].includes(v)) {
        const cat = ['Makeup', 'Skincare'].find(c => c.toLowerCase() === parts[1]) || 'All';
        const sub = Object.keys(SUB_LABEL).find(x => x.toLowerCase() === parts[2]) || 'All';
        shop = { col: v === 'shop' ? 'all' : v, cat, sub, sort: 'featured' };
        query = '';
        $('#searchInput').value = '';
        showView('shop');
        refreshShop();
    } else {
        showView('home');
    }
}

$('#filters').addEventListener('click', e => {
    const chip = e.target.closest('.chip');
    if (chip) setShop({ cat: chip.dataset.cat, sub: 'All' });
});
$('#subFilters').addEventListener('click', e => {
    const chip = e.target.closest('.chip');
    if (chip) setShop({ sub: chip.dataset.sub });
});
$('#sortSelect').addEventListener('change', e => setShop({ sort: e.target.value }));

$('#searchInput').addEventListener('input', e => {
    query = e.target.value;
    if (currentView !== 'shop') {
        if (!query.trim()) return;
        shop = { col: 'all', cat: 'All', sub: 'All', sort: 'featured' };
        history.pushState(null, '', '#shop');
        showView('shop');
        refreshShop();
    } else {
        renderShopHead();
        renderProducts();
    }
});
$('#headSearch').addEventListener('submit', e => { e.preventDefault(); $('#searchInput').blur(); });

document.addEventListener('click', e => {
    const a = e.target.closest('[data-nav]');
    if (!a) return;
    e.preventDefault();
    closeAll();
    $('#navSub').classList.remove('open');
    const view = a.dataset.nav;
    if (view === 'shop') {
        shop = { col: a.dataset.col || 'all', cat: a.dataset.cat || 'All', sub: a.dataset.sub || 'All', sort: 'featured' };
        query = '';
        $('#searchInput').value = '';
        history.pushState(null, '', shopHash());
        showView('shop');
        refreshShop();
    } else {
        history.pushState(null, '', '#' + view);
        showView(view);
    }
    if (document.activeElement) document.activeElement.blur();
});
window.addEventListener('popstate', routeFromHash);

// favorites need an account so they follow the customer to any device

async function toggleFavorite(id) {
    if (!state.user) {
        toast('sign in to save favorites');
        openAccount('login', 'Sign in to save favorites.');
        return;
    }
    try {
        const data = await api('favorites.php', { product_id: id });
        state.favorites = data.favorites;
        toast(state.favorites.includes(id) ? 'Added to favorites' : 'Removed from favorites');
        refreshFavUI();
        renderProducts();
        if ($('#productModal').classList.contains('open')) renderProductModal(id);
    } catch (err) {
        toast(err.message);
    }
}

document.addEventListener('click', e => {
    const fav = e.target.closest('[data-fav]');
    if (fav) {
        e.stopPropagation();
        toggleFavorite(+fav.dataset.fav);
        return;
    }
    const add = e.target.closest('[data-add]');
    if (add) {
        const id = +add.dataset.add;
        addToCart(id, 1);
        refreshCartUI();
        toast(getProduct(id).name + ' added to bag');
        return;
    }
    const view = e.target.closest('[data-view]');
    if (view) openProduct(+view.dataset.view);
});

// product modal

let modalQty = 1;
function openProduct(id) {
    modalQty = 1;
    renderProductModal(id);
    openPanel($('#productModal'));
}

function renderProductModal(id) {
    const p = getProduct(id);
    if (!p) return;
    $('#productModalBody').innerHTML = `
        <div class="pm">
            <div class="pm-img">${imgTag(p)}</div>
            <div class="pm-info">
                <span class="card-cat">${p.category} · ${SUB_LABEL[p.sub] || esc(p.sub)}</span>
                <h2>${esc(p.name)}</h2>
                <div class="rating"><span class="stars">${stars(p.rating)}</span> ${p.rating.toFixed(1)} / 5</div>
                <div class="pm-price">${priceHTML(p)}</div>
                <p class="pm-desc">${esc(p.description)}</p>
                <p class="muted">${p.stock < 1 ? 'Sold out' : p.stock <= 5 ? 'Only ' + p.stock + ' left' : 'In stock'}</p>
                <div>
                    <div class="card-cat" style="margin-bottom:8px">Quantity</div>
                    <div class="qty"><button data-mq="-1" aria-label="Decrease">&minus;</button><span id="mqVal">${modalQty}</span><button data-mq="1" aria-label="Increase">+</button></div>
                </div>
                <div class="pm-actions">
                    <button class="btn" data-modal-add="${p.id}" ${p.stock < 1 ? 'disabled' : ''}>Add to Bag</button>
                    <button class="fav fav-lg ${isFav(id) ? 'on' : ''}" data-fav="${p.id}" aria-label="Toggle favorite">${isFav(id) ? '♥' : '♡'}</button>
                </div>
            </div>
        </div>`;
}
document.addEventListener('click', e => {
    const mq = e.target.closest('[data-mq]');
    if (mq) {
        modalQty = Math.max(1, Math.min(99, modalQty + +mq.dataset.mq));
        $('#mqVal').textContent = modalQty;
    }
    const ma = e.target.closest('[data-modal-add]');
    if (ma) {
        const id = +ma.dataset.modalAdd;
        addToCart(id, modalQty);
        refreshCartUI();
        toast(`${modalQty} × ${getProduct(id).name} added to bag`);
        openPanel($('#cartDrawer'));
    }
});

// coupon box, shared by the bag and checkout

function couponBox(totals) {
    const picks = state.coupons.filter(c => c.code !== state.couponCode);
    let note = '';
    if (state.couponCode && totals.coupon_error) note = `<div class="cpn-msg">${esc(totals.coupon_error)}</div>`;
    else if (state.couponCode && totals.coupon) note = `<div class="cpn-msg ok">${esc(totals.coupon)} applied. <button class="link-btn" data-remove-coupon>Remove</button></div>`;
    else if (!state.user) note = '<div class="cpn-msg muted">Sign in to use a coupon.</div>';
    return `<div class="cpn-box">
        <form class="cpn-form" data-coupon-form>
            <input name="code" placeholder="Coupon code" value="${esc(state.couponCode)}" autocomplete="off" aria-label="Coupon code">
            <button class="btn btn-sm" type="submit">Apply</button>
        </form>${note}
        ${picks.length && !state.couponCode ? `<div class="cpn-pick">${picks.map(c => `<button type="button" data-use-coupon="${esc(c.code)}">${esc(c.code)}</button>`).join('')}</div>` : ''}
    </div>`;
}

document.addEventListener('submit', async e => {
    const form = e.target.closest('[data-coupon-form]');
    if (!form) return;
    e.preventDefault();
    const code = form.code.value.trim().toUpperCase();
    state.couponCode = code;
    await afterCouponChange();
});
document.addEventListener('click', async e => {
    const use = e.target.closest('[data-use-coupon]');
    if (use) { state.couponCode = use.dataset.useCoupon; await afterCouponChange(); }
    if (e.target.closest('[data-remove-coupon]')) { state.couponCode = ''; await afterCouponChange(); }
});

async function afterCouponChange() {
    refreshCartUI();
    if ($('#checkoutModal').classList.contains('open') && step === 1) await renderCheckout();
}

// bag

async function refreshCartUI() {
    const lines = cartLines();
    const count = lines.reduce((s, l) => s + l.quantity, 0);
    const badge = $('#cartCount');
    badge.textContent = count;
    badge.classList.toggle('zero', count === 0);

    if (!lines.length) {
        $('#cartBody').innerHTML = '<div class="drawer-empty"><h4>Your bag is currently empty.</h4><button class="btn btn-sm" data-close>Continue Shopping</button></div>';
        $('#cartFoot').innerHTML = '';
        return;
    }
    $('#cartBody').innerHTML = lines.map(l => `
        <div class="line">
            <div class="line-img">${imgTag(l.product)}</div>
            <div>
                <div class="line-name">${esc(l.product.name)}</div>
                <div class="line-meta">${peso(l.product.price)} each</div>
                <div class="line-ctrl">
                    <div class="qty">
                        <button data-cq="${l.productId}" data-d="-1" aria-label="Decrease">&minus;</button>
                        <span>${l.quantity}</span>
                        <button data-cq="${l.productId}" data-d="1" aria-label="Increase">+</button>
                    </div>
                    <strong>${peso(l.product.price * l.quantity)}</strong>
                </div>
                <button class="link-btn" data-rm="${l.productId}" style="margin-top:8px">Remove</button>
            </div>
        </div>`).join('');

    const t = await getTotals();
    if (!cartLines().length) return;
    $('#cartFoot').innerHTML = `
        ${couponBox(t)}
        <div class="row"><span>Subtotal</span><span>${peso(t.subtotal)}</span></div>
        ${t.discount > 0 ? `<div class="row save"><span>Coupon ${esc(t.coupon)}</span><span>- ${peso(t.discount)}</span></div>` : ''}
        <div class="row"><span>Shipping</span><span>${t.shipping ? peso(t.shipping) : 'Free'}</span></div>
        ${t.shipping ? `<div class="row" style="font-size:.78rem;color:#6f6f6f"><span>Free shipping on orders over ${peso(state.settings.freeShippingMin)}</span></div>` : ''}
        <div class="row total"><span>Total</span><span>${peso(t.total)}</span></div>
        <button class="btn btn-block" id="goCheckout">Checkout</button>
        <button class="link-btn" data-close style="display:block;margin:14px auto 0">Continue Shopping</button>`;
}

document.addEventListener('click', e => {
    const cq = e.target.closest('[data-cq]');
    if (cq) {
        const id = +cq.dataset.cq;
        const item = readCart().find(i => i.productId === id);
        if (item) setQuantity(id, item.quantity + +cq.dataset.d);
        refreshCartUI();
    }
    const rm = e.target.closest('[data-rm]');
    if (rm) {
        setQuantity(+rm.dataset.rm, 0);
        refreshCartUI();
        toast('Item removed');
    }
    if (e.target.closest('#goCheckout')) startCheckout();
});
$('#openCart').addEventListener('click', () => { refreshCartUI(); openPanel($('#cartDrawer')); });

// favorites drawer

function refreshFavUI() {
    const favs = state.favorites.map(getProduct).filter(Boolean);
    const badge = $('#favCount');
    badge.textContent = favs.length;
    badge.classList.toggle('zero', favs.length === 0);
    $('#favBody').innerHTML = favs.length ? favs.map(p => `
        <div class="line">
            <div class="line-img" data-view="${p.id}" style="cursor:pointer">${imgTag(p)}</div>
            <div>
                <div class="line-name" data-view="${p.id}" style="cursor:pointer">${esc(p.name)}</div>
                <div class="line-meta">${p.category} · ${peso(p.price)}</div>
                <div class="line-ctrl">
                    <button class="btn btn-sm" data-add="${p.id}" ${p.stock < 1 ? 'disabled' : ''}>Add to Bag</button>
                    <button class="link-btn" data-fav="${p.id}">Remove</button>
                </div>
            </div>
        </div>`).join('')
        : `<div class="drawer-empty"><h4>${state.user ? 'No favorites yet.' : 'Sign in to see your favorites.'}</h4>
            <p style="margin-bottom:20px;color:#6f6f6f;font-size:.9rem">${state.user ? 'Tap the heart on any product to save it here.' : 'Your favorites are saved to your account.'}</p>
            <button class="btn btn-sm" ${state.user ? 'data-close' : 'data-open="account"'}>${state.user ? 'Start Browsing' : 'Sign In'}</button></div>`;
}
$('#openFav').addEventListener('click', () => { refreshFavUI(); openPanel($('#favDrawer')); });

// account

function couponCard(c) {
    return `<div class="cpn">
        <div class="cpn-main"><strong>${esc(c.text)}</strong>
            <small>${esc(c.description || 'coupon')}${c.min_spend ? ' · min. spend ' + peso(c.min_spend) : ''}${c.expires_at ? ' · until ' + new Date(c.expires_at.replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : ''}</small></div>
        <div class="cpn-stub"><div><code>${esc(c.code)}</code><br><button data-use-coupon="${esc(c.code)}" data-close>Use</button></div></div>
    </div>`;
}

function orderCard(o) {
    return `<div class="order-card">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
            <h4>${esc(o.order_no)}</h4><span class="status">${esc(o.status)}</span>
        </div>
        <small>${new Date(o.created_at.replace(' ', 'T')).toLocaleString('en-PH')} · ${esc(o.payment_method)}</small>
        <div style="margin-top:10px;font-size:.88rem">${o.items.map(i => `${esc(i.name)} × ${i.quantity}`).join('<br>')}</div>
        ${o.discount > 0 ? `<div style="margin-top:8px;font-size:.82rem">Coupon ${esc(o.coupon_code)}: - ${peso(o.discount)}</div>` : ''}
        <div style="margin-top:10px;font-weight:700">${peso(o.total)}</div>
    </div>`;
}

async function openAccount(tab = 'login', notice = '') {
    openPanel($('#accountModal'));
    if (state.user) return renderAccount();
    renderAuth(tab, notice);
}

function renderAuth(tab, notice = '') {
    const reg = tab === 'register';
    $('#accountBody').innerHTML = `
        <h2>${reg ? 'Create Account' : 'Welcome Back'}</h2>
        <div class="acct-tabs"><button class="${reg ? '' : 'on'}" data-tab="login">Sign In</button><button class="${reg ? 'on' : ''}" data-tab="register">Create Account</button></div>
        ${notice ? `<div class="notice-box">${esc(notice)}</div>` : ''}
        ${reg ? `<div class="notice-box">New members get a <strong>${state.settings.welcomePercent}% off</strong> welcome coupon right after signing up.</div>` : ''}
        <div class="form-error" id="authError"></div>
        <form id="authForm" novalidate>
            ${reg ? `<div class="field"><label for="a_name">Full Name</label><input id="a_name" name="name" autocomplete="name"></div>` : ''}
            <div class="field"><label for="a_email">Email</label><input id="a_email" name="email" type="email" autocomplete="email"></div>
            ${reg ? `<div class="field"><label for="a_phone">Phone (optional)</label><input id="a_phone" name="phone" type="tel" autocomplete="tel"></div>` : ''}
            <div class="field"><label for="a_pass">Password</label><input id="a_pass" name="password" type="password" autocomplete="${reg ? 'new-password' : 'current-password'}"></div>
            <div class="co-actions"><button class="btn" type="submit">${reg ? 'Create Account' : 'Sign In'}</button></div>
        </form>`;
    $('#authForm').addEventListener('submit', e => submitAuth(e, reg));
}

async function submitAuth(e, reg) {
    e.preventDefault();
    const f = e.target;
    const error = $('#authError');
    error.textContent = '';
    const body = { email: f.email.value, password: f.password.value };
    if (reg) { body.name = f.name.value; body.phone = f.phone.value; }
    try {
        const data = await api('auth.php?action=' + (reg ? 'register' : 'login'), body);
        applyMe(data);
        renderProducts();
        if (data.welcome_coupon) return renderWelcome(data.welcome_coupon);
        toast('Welcome back, ' + data.user.name.split(' ')[0]);
        finishLogin();
    } catch (err) {
        error.textContent = err.message;
    }
}

function renderWelcome(w) {
    $('#accountBody').innerHTML = `
        <div class="welcome-pop">
            <div class="confirm"><div class="tick">♥</div></div>
            <h2 style="margin-bottom:6px">You're in!</h2>
            <p class="muted">Here is your welcome coupon: <strong>${esc(w.text)}</strong> your first order.</p>
            <div class="code">${esc(w.code)}</div>
            <p class="muted">Valid until ${new Date(w.expires_at.replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })}. It is also saved in your account.</p>
            <div class="co-actions"><button class="btn" id="useWelcome">Use it now</button></div>
        </div>`;
    $('#useWelcome').addEventListener('click', () => {
        state.couponCode = w.code;
        finishLogin(true);
    });
}

function finishLogin(useCoupon = false) {
    const next = state.afterLogin;
    state.afterLogin = null;
    refreshCartUI();
    if (next === 'checkout' && cartLines().length) return startCheckout();
    if (useCoupon) { openPanel($('#cartDrawer')); return; }
    closeAll();
}

function renderAccount() {
    const u = state.user;
    $('#accountBody').innerHTML = `
        <h2>My Account</h2>
        <div class="who"><div><strong>${esc(u.name)}</strong><small>${esc(u.email)}</small></div>
            <div>${u.role === 'admin' ? '<a class="btn btn-sm" href="admin/index.php" style="margin-right:8px">Admin</a>' : ''}<button class="btn btn-sm btn-back" id="signOut" style="background:#fff;color:#1a1a1a">Sign Out</button></div></div>
        <div class="sec-title">My coupons</div>
        ${state.coupons.length ? state.coupons.map(couponCard).join('') : '<p class="muted">No coupons right now. New offers will show up here.</p>'}
        <div class="sec-title">My orders</div>
        <div id="myOrders"><p class="muted">Loading…</p></div>`;
    $('#signOut').addEventListener('click', signOut);
    api('orders.php?action=mine').then(d => {
        $('#myOrders').innerHTML = d.orders.length ? d.orders.map(orderCard).join('') : '<p class="muted">You have not placed any orders yet.</p>';
    }).catch(() => { $('#myOrders').innerHTML = '<p class="muted">Could not load your orders.</p>'; });
}

async function signOut() {
    try {
        applyMe(await api('auth.php?action=logout', {}));
    } catch (err) {
        return toast(err.message);
    }
    state.couponCode = '';
    renderProducts();
    refreshCartUI();
    closeAll();
    toast('You are signed out');
}

document.addEventListener('click', e => {
    const tab = e.target.closest('[data-tab]');
    if (tab) renderAuth(tab.dataset.tab);
});
$('#openAccount').addEventListener('click', () => openAccount());

// checkout: review, details, shipping, payment. login required so coupons and orders belong to someone

const STEP_NAMES = ['Review', 'Details', 'Shipping', 'Payment'];
let step = 1;
let checkout = { name: '', email: '', phone: '', address: '', city: '', postalCode: '', payment: 'Cash on Delivery' };

function startCheckout() {
    if (!cartLines().length) return;
    if (!state.user) {
        state.afterLogin = 'checkout';
        toast('sign in to check out');
        return openAccount('login', 'Please sign in or create an account to check out. New accounts get a welcome coupon.');
    }
    step = 1;
    checkout.name = checkout.name || state.user.name;
    checkout.email = checkout.email || state.user.email;
    checkout.phone = checkout.phone || state.user.phone || '';
    openPanel($('#checkoutModal'));
    renderCheckout();
}

async function renderCheckout() {
    const lines = cartLines();
    let body = '';
    let t = null;

    if (step === 1 || step === 4) t = await getTotals();

    if (step === 1) {
        body = '<h2>Review Your Order</h2>' +
            lines.map(l => `<div class="summary-line"><div>${esc(l.product.name)}<small>${l.quantity} × ${peso(l.product.price)}</small></div><strong>${peso(l.quantity * l.product.price)}</strong></div>`).join('') +
            `<div style="margin-top:16px">${couponBox(t)}</div>
             <div class="summary-line"><span>Subtotal</span><span>${peso(t.subtotal)}</span></div>
             ${t.discount > 0 ? `<div class="summary-line"><span>Coupon ${esc(t.coupon)}</span><span>- ${peso(t.discount)}</span></div>` : ''}
             <div class="summary-line"><span>Shipping</span><span>${t.shipping ? peso(t.shipping) : 'Free'}</span></div>
             <div class="summary-line" style="font-weight:700;font-size:1.05rem;border:none"><span>Total</span><span>${peso(t.total)}</span></div>`;
    } else if (step === 2) {
        body = `<h2>Your Details</h2>
            ${field('name', 'Full Name', 'text', 'Juana Dela Cruz')}
            ${field('email', 'Email', 'email', 'you@example.com')}
            ${field('phone', 'Phone Number', 'tel', '09XX XXX XXXX')}`;
    } else if (step === 3) {
        body = `<h2>Shipping Address</h2>
            ${field('address', 'Address', 'text', 'House no., street, barangay')}
            <div class="two">${field('city', 'City', 'text', '')}${field('postalCode', 'Postal Code', 'text', '')}</div>`;
    } else {
        const opts = [
            ['Cash on Delivery', 'Pay when your order arrives'],
            ['GCash', 'Pay with your GCash wallet'],
            ['Credit/Debit Card', 'Visa or Mastercard'],
        ];
        body = '<h2>Payment Method</h2>' + opts.map(([n, d]) => `
            <label class="pay-opt ${checkout.payment === n ? 'sel' : ''}">
                <input type="radio" name="pay" value="${n}" ${checkout.payment === n ? 'checked' : ''}>
                <span><strong>${n}</strong><small>${d}</small></span>
            </label>`).join('') +
            `<div class="ship-note">Online payment is not connected yet. GCash and card orders are confirmed by our team before shipping.</div>
             <div class="form-error" id="placeError" style="margin-top:14px"></div>
             ${t.discount > 0 ? `<div class="summary-line"><span>Coupon ${esc(t.coupon)}</span><span>- ${peso(t.discount)}</span></div>` : ''}
             <div class="summary-line" style="font-weight:700;font-size:1.05rem;border:none;margin-top:10px"><span>Total</span><span>${peso(t.total)}</span></div>`;
    }

    const stepper = `<div class="steps">${STEP_NAMES.map((n, i) => `<div class="${i + 1 === step ? 'active' : i + 1 < step ? 'done' : ''}">${i + 1}. ${n}</div>`).join('')}</div>`;
    const actions = `<div class="co-actions">
        ${step > 1 ? '<button class="btn btn-back" id="coBack">Back</button>' : '<button class="btn btn-back" data-close>Cancel</button>'}
        <button class="btn" id="coNext">${step === 4 ? 'Place Order' : 'Continue'}</button>
    </div>`;
    $('#checkoutBody').innerHTML = stepper + body + actions;
}

function field(key, label, type, placeholder) {
    return `<div class="field" data-field="${key}">
        <label for="f_${key}">${label}</label>
        <input id="f_${key}" type="${type}" placeholder="${placeholder}" value="${esc(checkout[key])}">
        <div class="msg"></div></div>`;
}

const VALIDATORS = {
    name: v => v.trim().length >= 2 || 'Please enter your full name.',
    email: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) || 'Please enter a valid email address.',
    phone: v => /^[0-9+\-\s()]{7,20}$/.test(v.trim()) || 'Please enter a valid phone number.',
    address: v => v.trim().length >= 5 || 'Please enter your address.',
    city: v => v.trim().length >= 2 || 'Please enter your city.',
    postalCode: v => /^[0-9A-Za-z\- ]{3,10}$/.test(v.trim()) || 'Please enter a valid postal code.',
};
const STEP_FIELDS = { 2: ['name', 'email', 'phone'], 3: ['address', 'city', 'postalCode'] };

function validateStep() {
    let ok = true;
    (STEP_FIELDS[step] || []).forEach(k => {
        const input = $('#f_' + k);
        const wrap = input.closest('.field');
        checkout[k] = input.value;
        const result = VALIDATORS[k](input.value);
        wrap.classList.toggle('err', result !== true);
        wrap.querySelector('.msg').textContent = result === true ? '' : result;
        if (result !== true) ok = false;
    });
    return ok;
}

document.addEventListener('change', e => {
    if (e.target.name === 'pay') {
        checkout.payment = e.target.value;
        $$('.pay-opt').forEach(o => o.classList.toggle('sel', o.querySelector('input').checked));
    }
});
document.addEventListener('click', async e => {
    if (e.target.id === 'coBack') {
        (STEP_FIELDS[step] || []).forEach(k => { checkout[k] = $('#f_' + k).value; });
        step--;
        renderCheckout();
    }
    if (e.target.id === 'coNext') {
        if (!validateStep()) return;
        if (step < 4) { step++; renderCheckout(); } else placeOrder(e.target);
    }
});

async function placeOrder(button) {
    const lines = cartLines();
    if (!lines.length) return;
    button.disabled = true;
    try {
        const data = await api('checkout.php', {
            action: 'place',
            items: lines.map(l => ({ id: l.productId, qty: l.quantity })),
            coupon: state.couponCode,
            customer: {
                name: checkout.name.trim(), email: checkout.email.trim(), phone: checkout.phone.trim(),
                address: checkout.address.trim(), city: checkout.city.trim(), postal_code: checkout.postalCode.trim(),
                payment: checkout.payment,
            },
        });
        saveCart([]);
        state.couponCode = '';
        const me = await api('auth.php?action=me');
        applyMe(me);
        const fresh = await api('products.php');
        state.products = fresh.products;
        renderProducts();
        refreshCartUI();
        renderConfirmation(data.order);
    } catch (err) {
        button.disabled = false;
        if (err.status === 401) {
            state.user = null;
            state.afterLogin = 'checkout';
            return openAccount('login', 'Your session ended. Please sign in again to finish your order.');
        }
        const box = $('#placeError');
        if (box) box.textContent = err.message;
    }
}

function renderConfirmation(o) {
    $('#checkoutBody').innerHTML = `
        <div class="confirm">
            <div class="tick">♥</div>
            <h2 style="margin-bottom:6px">Order placed successfully!</h2>
            <p style="color:#6f6f6f;font-size:.9rem">Thank you, ${esc(o.customer_name)}. Keep this order number to track your delivery.</p>
            <div class="ordno">${esc(o.order_no)}</div>
            <div class="box">
                ${o.items.map(i => `<div class="kv"><span>${esc(i.name)} × ${i.quantity}</span><span>${peso(i.price * i.quantity)}</span></div>`).join('')}
                <div class="kv" style="border-top:1px solid #ececec"><span>Subtotal</span><span>${peso(o.subtotal)}</span></div>
                ${o.discount > 0 ? `<div class="kv"><span>Coupon ${esc(o.coupon_code)}</span><span>- ${peso(o.discount)}</span></div>` : ''}
                <div class="kv"><span>Shipping</span><span>${o.shipping_fee ? peso(o.shipping_fee) : 'Free'}</span></div>
                <div class="kv" style="font-weight:700"><span style="color:#1a1a1a">Total</span><span>${peso(o.total)}</span></div>
                <div class="kv"><span>Payment</span><span>${esc(o.payment_method)}</span></div>
                <div class="kv"><span>Ship to</span><span style="text-align:right">${esc(o.address)}</span></div>
                <div class="kv"><span>Status</span><span class="status">${esc(o.status)}</span></div>
            </div>
            <div class="co-actions"><button class="btn" data-close>Continue Shopping</button></div>
        </div>`;
}

// track an order with the order number and the email used

$('#trackForm').addEventListener('submit', async e => {
    e.preventDefault();
    const no = $('#trackInput').value.trim();
    const email = $('#trackEmail').value.trim();
    try {
        const d = await api(`orders.php?action=track&order_no=${encodeURIComponent(no)}&email=${encodeURIComponent(email)}`);
        $('#ordersBody').innerHTML = d.orders.map(orderCard).join('');
    } catch (err) {
        $('#ordersBody').innerHTML = `<p class="muted">${esc(err.message)}</p>`;
    }
});

document.addEventListener('click', e => {
    const o = e.target.closest('[data-open]');
    if (!o) return;
    e.preventDefault();
    $('#navSub').classList.remove('open');
    if (o.dataset.open === 'help') openPanel($('#helpModal'));
    if (o.dataset.open === 'account') openAccount(state.user ? undefined : 'register');
    if (o.dataset.open === 'track') {
        $('#trackInput').value = '';
        $('#trackEmail').value = state.user ? state.user.email : '';
        $('#ordersBody').innerHTML = '';
        openPanel($('#ordersModal'));
    }
});

$('#menuToggle').addEventListener('click', () => $('#navSub').classList.toggle('open'));

// start

function applySettings() {
    const s = state.settings;
    $('#topNote').textContent = `Free shipping on orders over ${peso(s.freeShippingMin)} · Cash on delivery available`;
    $('#welcomePct').textContent = s.welcomePercent + '%';
    $('#faqShip').textContent = `Shipping is a flat ${peso(s.shippingFee)}, and free on orders of ${peso(s.freeShippingMin)} or more.`;
}

async function init() {
    try {
        const [catalog, me] = await Promise.all([api('products.php'), api('auth.php?action=me')]);
        state.products = catalog.products;
        state.settings = catalog.settings;
        applyMe(me);
    } catch (err) {
        $('#products').innerHTML = `<div class="empty-state"><h3>The store is unavailable</h3><p>${esc(err.message)}</p></div>`;
    }
    applySettings();
    routeFromHash();
    renderProducts();
    refreshCartUI();
    refreshFavUI();
}
init();

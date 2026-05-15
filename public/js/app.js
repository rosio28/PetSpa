// ============================================================
// PET SPA — app.js  (utilidades globales)
// ============================================================

const API = window.location.origin;

const Auth = {
  token:    () => localStorage.getItem('ps_token'),
  refresh:  () => localStorage.getItem('ps_refresh'),
  user:     () => { try { return JSON.parse(localStorage.getItem('ps_user') || 'null'); } catch { return null; } },
  save:     (token, refresh, user) => {
    localStorage.setItem('ps_token',   token);
    localStorage.setItem('ps_refresh', refresh);
    localStorage.setItem('ps_user',    JSON.stringify(user));
  },
  clear:    () => ['ps_token','ps_refresh','ps_user'].forEach(k => localStorage.removeItem(k)),
  isLogged: () => !!localStorage.getItem('ps_token'),
  rol:      () => Auth.user()?.rol || '',
};

let _refreshing = false;
let _refreshPromise = null;

async function doRefresh() {
  if (_refreshing) return _refreshPromise;
  _refreshing = true;
  _refreshPromise = (async () => {
    const rToken = Auth.refresh();
    if (!rToken) return false;
    try {
      const r = await fetch(API + '/auth/refresh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rToken }),
      });
      if (!r.ok) return false;
      const d = await r.json();
      if (d.success && d.data?.token) {
        Auth.save(d.data.token, d.data.refresh_token, Auth.user());
        return true;
      }
      return false;
    } catch { return false; }
    finally { _refreshing = false; _refreshPromise = null; }
  })();
  return _refreshPromise;
}

const Http = {
  async req(method, path, body = null, isForm = false) {
    const buildHeaders = () => {
      const h = {};
      const t = Auth.token();
      if (t) h['Authorization'] = 'Bearer ' + t;
      if (!isForm) h['Content-Type'] = 'application/json';
      return h;
    };
    const buildOpts = () => {
      const o = { method, headers: buildHeaders() };
      if (body) o.body = isForm ? body : JSON.stringify(body);
      return o;
    };

    let res;
    try {
      res = await fetch(API + path, buildOpts());
    } catch {
      Toast.error('Sin conexion con el servidor');
      return null;
    }

    if (res.status === 401) {
      const ok = await doRefresh();
      if (!ok) { Auth.clear(); window.location.href = '/login.html'; return null; }
      try { res = await fetch(API + path, buildOpts()); } catch { return null; }
      if (res.status === 401) { Auth.clear(); window.location.href = '/login.html'; return null; }
    }

    try {
      return await res.json();
    } catch {
      // Solo error visible si es fallo 5xx, no 403/404 silenciosos
      if (res.status >= 500) Toast.error('Error del servidor (' + res.status + ')');
      return null;
    }
  },

  get:      (path)       => Http.req('GET',    path),
  post:     (path, body) => Http.req('POST',   path, body),
  put:      (path, body) => Http.req('PUT',    path, body),
  delete:   (path)       => Http.req('DELETE', path),
  postForm: (path, fd)   => Http.req('POST',   path, fd, true),
};

const Toast = {
  show(msg, type, duration) {
    type = type || 'default'; duration = duration || 3500;
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    el.innerHTML = '<span>' + icon + '</span><span>' + msg + '</span>';
    c.appendChild(el);
    setTimeout(() => { el.style.animation = 'fadeOut .3s ease forwards'; setTimeout(() => el.remove(), 320); }, duration);
  },
  success: (m, d) => Toast.show(m, 'success', d),
  error:   (m, d) => Toast.show(m, 'error', d),
  info:    (m, d) => Toast.show(m, 'default', d || 4000),
};

const Router = {
  routes: {},
  register(name, fn) { this.routes[name] = fn; },
  go(name, params) {
    params = params || {};
    const fn = this.routes[name];
    if (!fn) { console.warn('[Router] Pagina no registrada:', name); return; }
    document.querySelectorAll('.nav-item').forEach(el => el.classList.toggle('active', el.dataset.page === name));
    const c = document.getElementById('page-content');
    if (c) c.innerHTML = '<div class="flex-center" style="padding:80px"><div class="spinner spinner-dark"></div></div>';
    try { fn(params); }
    catch (e) { console.error('[Router]', name, e); if (c) c.innerHTML = '<div class="alert alert-error">Error: ' + e.message + '</div>'; }
  },
};

document.addEventListener('click', e => {
  if (e.target.closest('.hamburger')) document.querySelector('.sidebar')?.classList.toggle('open');
  else if (window.innerWidth <= 768 && !e.target.closest('.sidebar') && !e.target.closest('.hamburger'))
    document.querySelector('.sidebar')?.classList.remove('open');
});

let _inactiveTimer;
function resetInactivity() {
  clearTimeout(_inactiveTimer);
  if (!Auth.isLogged()) return;
  _inactiveTimer = setTimeout(() => {
    Toast.info('Sesion cerrada por inactividad');
    Auth.clear();
    setTimeout(() => window.location.href = '/login.html', 1500);
  }, 30 * 60 * 1000);
}
['mousemove','keydown','click','scroll','touchstart'].forEach(e =>
  document.addEventListener(e, resetInactivity, { passive: true })
);

function badgeEstadoCita(estado) {
  const map = {
    agendada:    ['badge-info',    '📅 Agendada'],
    confirmada:  ['badge-sage',    '✓ Confirmada'],
    en_progreso: ['badge-warning', '▶ En progreso'],
    completada:  ['badge-dark',    '✔ Completada'],
    cancelada:   ['badge-terra',   '✕ Cancelada'],
    no_asistio:  ['badge-gray',    '— No asistio'],
  };
  const [cls, label] = map[estado] || ['badge-gray', estado];
  return '<span class="badge ' + cls + '">' + label + '</span>';
}

function formatFecha(str) {
  if (!str) return '—';
  try { return new Date(str).toLocaleString('es-BO', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }); }
  catch { return str; }
}

function formatMoneda(val) {
  return 'Bs ' + parseFloat(val || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function iniciales(nombre) {
  if (!nombre) return '?';
  return nombre.trim().split(/\s+/).slice(0,2).map(w => w[0]?.toUpperCase()||'').join('');
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) { e.target.classList.remove('open'); document.body.style.overflow = ''; }
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => { m.classList.remove('open'); document.body.style.overflow = ''; });
});

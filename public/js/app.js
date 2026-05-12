const API = window.location.origin;

const Auth = {
  token:    () => localStorage.getItem('ps_token'),
  refresh:  () => localStorage.getItem('ps_refresh'),
  user:     () => { try { return JSON.parse(localStorage.getItem('ps_user') || 'null'); } catch(e){ return null; } },
  save:     (token, refresh, user) => {
    localStorage.setItem('ps_token',   token);
    localStorage.setItem('ps_refresh', refresh);
    localStorage.setItem('ps_user',    JSON.stringify(user));
  },
  clear:    () => { ['ps_token','ps_refresh','ps_user'].forEach(k => localStorage.removeItem(k)); },
  isLogged: () => !!localStorage.getItem('ps_token'),
  rol:      () => { const u = Auth.user(); return u?.rol || ''; },
};

// Variable global para evitar múltiples refresh simultáneos
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
        body: JSON.stringify({ refresh_token: rToken })
      });
      const d = await r.json();
      if (d.success && d.data?.token) {
        Auth.save(d.data.token, d.data.refresh_token, Auth.user());
        return true;
      }
      return false;
    } catch(e) {
      return false;
    } finally {
      _refreshing = false;
      _refreshPromise = null;
    }
  })();
  return _refreshPromise;
}

const Http = {
  async req(method, path, body = null, isForm = false) {
    const makeHeaders = () => {
      const h = {};
      const t = Auth.token();
      if (t) h['Authorization'] = 'Bearer ' + t;
      if (!isForm) h['Content-Type'] = 'application/json';
      return h;
    };

    const makeOpts = () => {
      const o = { method, headers: makeHeaders() };
      if (body) o.body = isForm ? body : JSON.stringify(body);
      return o;
    };

    let res;
    try {
      res = await fetch(API + path, makeOpts());
    } catch(e) {
      Toast.error('Error de conexión');
      return null;
    }

    if (res.status === 401) {
      // Intentar renovar token
      const ok = await doRefresh();
      if (!ok) {
        Auth.clear();
        window.location.href = '/login.html';
        return null;
      }
      // Reintentar con el token nuevo (makeOpts() lee Auth.token() de nuevo)
      try {
        res = await fetch(API + path, makeOpts());
      } catch(e) {
        return null;
      }
      if (res.status === 401) {
        Auth.clear();
        window.location.href = '/login.html';
        return null;
      }
    }

    try {
      return await res.json();
    } catch(e) {
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
    type = type || 'default';
    duration = duration || 3500;
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = '<span>' + (type==='success'?'✓':type==='error'?'✕':'•') + '</span><span>' + msg + '</span>';
    c.appendChild(el);
    setTimeout(() => { el.style.animation='fadeOut .3s ease forwards'; setTimeout(()=>el.remove(),320); }, duration);
  },
  success: (m) => Toast.show(m,'success'),
  error:   (m) => Toast.show(m,'error'),
};

const Router = {
  routes: {},
  register(name, fn) { this.routes[name] = fn; },
  go(name, params) {
    params = params || {};
    const fn = this.routes[name];
    if (!fn) { console.warn('Página no registrada:', name); return; }
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.toggle('active', el.dataset.page === name);
    });
    const c = document.getElementById('page-content');
    if (c) c.innerHTML = '<div class="flex-center" style="padding:80px"><div class="spinner spinner-dark"></div></div>';
    try { fn(params); } catch(e) {
      console.error('Error en página', name, e);
      if (c) c.innerHTML = '<div class="alert alert-error">Error: ' + e.message + '</div>';
    }
  },
};

document.addEventListener('click', e => {
  if (e.target.closest('.hamburger')) document.querySelector('.sidebar')?.classList.toggle('open');
});

let _inactiveTimer;
function resetInactivity() {
  clearTimeout(_inactiveTimer);
  if (Auth.isLogged()) {
    _inactiveTimer = setTimeout(() => {
      Toast.show('Sesión cerrada por inactividad');
      Auth.clear();
      window.location.href = '/login.html';
    }, 30 * 60 * 1000);
  }
}
['mousemove','keydown','click','scroll'].forEach(e => document.addEventListener(e, resetInactivity));

function badgeEstadoCita(estado) {
  const map = {
    agendada:    ['badge-info',    'Agendada'],
    confirmada:  ['badge-sage',    'Confirmada'],
    en_progreso: ['badge-warning', 'En progreso'],
    completada:  ['badge-dark',    'Completada'],
    cancelada:   ['badge-terra',   'Cancelada'],
    no_asistio:  ['badge-gray',    'No asistió'],
  };
  const [cls, label] = map[estado] || ['badge-gray', estado];
  return '<span class="badge ' + cls + '">' + label + '</span>';
}

function formatFecha(str) {
  if (!str) return '—';
  try {
    return new Date(str).toLocaleString('es-BO', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
  } catch(e) { return str; }
}
function formatMoneda(val) { return 'Bs ' + parseFloat(val||0).toFixed(2); }
function iniciales(nombre) {
  if (!nombre) return '?';
  return nombre.split(' ').slice(0,2).map(w=>w[0]).join('').toUpperCase();
}
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
});
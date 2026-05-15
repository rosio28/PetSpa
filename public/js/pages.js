// ============================================================
// PET SPA — PÁGINAS SPA (pages.js)  — versión completa
// ============================================================

// ══════════════════════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════════════════════
Router.register('dashboard', async () => {
  const res = await Http.get('/reportes/dashboard');
  const c   = document.getElementById('page-content');
  if (!res?.success) {
    c.innerHTML = '<div class="alert alert-error">Error al cargar dashboard: ' + (res?.message || 'Verifica la conexión.') + '</div>';
    return;
  }
  const d   = res.data;
  const rol = d.rol || Auth.rol();

  // ── CLIENTE ──────────────────────────────────────────────
  if (rol === 'cliente') {
    c.innerHTML = `
      <div class="stats-grid">
        <div class="stat-card accent-sage">
          <div class="stat-label">Mis mascotas</div>
          <div class="stat-value">${d.total_mascotas ?? 0}</div>
          <div class="stat-sub">registradas</div>
        </div>
        <div class="stat-card accent-terra">
          <div class="stat-label">Citas completadas</div>
          <div class="stat-value">${d.citas_total ?? 0}</div>
          <div class="stat-sub">historial total</div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <span class="card-title">Mis próximas citas</span>
          <button class="btn btn-primary btn-sm" onclick="window.navigate('citas','Mis Citas')">+ Agendar cita</button>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Mascota</th><th>Servicio</th><th>Groomer</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
              ${!d.citas_proximas?.length
                ? '<tr><td colspan="5" style="text-align:center;color:var(--gray-light);padding:40px">Sin citas próximas. <a href="#" onclick="window.navigate(\'citas\',\'Mis Citas\')" style="color:var(--sage-dark)">Agendar una →</a></td></tr>'
                : d.citas_proximas.map(ci => `<tr>
                    <td><strong>${ci.mascota}</strong></td>
                    <td>${ci.servicio}</td><td>${ci.groomer}</td>
                    <td>${formatFecha(ci.fecha_hora_inicio)}</td>
                    <td>${badgeEstadoCita(ci.estado)}</td>
                  </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
    return;
  }

  // ── GROOMER ──────────────────────────────────────────────
  if (rol === 'groomer') {
    c.innerHTML = `
      <div class="stats-grid">
        <div class="stat-card accent-sage">
          <div class="stat-label">Citas hoy</div>
          <div class="stat-value">${d.citas_hoy ?? 0}</div>
          <div class="stat-sub">asignadas</div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Mis próximas citas</span></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Mascota</th><th>Raza</th><th>Servicio</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead>
            <tbody>
              ${!d.proximas_citas?.length
                ? '<tr><td colspan="6" style="text-align:center;color:var(--gray-light);padding:40px">Sin citas próximas</td></tr>'
                : d.proximas_citas.map(ci => `<tr>
                    <td><strong>${ci.mascota}</strong></td>
                    <td class="text-muted text-small">${ci.raza||'—'}</td>
                    <td>${ci.servicio}</td>
                    <td>${formatFecha(ci.fecha_hora_inicio)}</td>
                    <td>${badgeEstadoCita(ci.estado)}</td>
                    <td>${ci.estado==='confirmada'?`<button class="btn btn-primary btn-sm" onclick="cambiarEstadoCita(${ci.id},'en_progreso')">▶ Iniciar</button>`:''}
                        ${ci.estado==='en_progreso'?`<button class="btn btn-primary btn-sm" onclick="window.navigate('fichas','Fichas')">📋 Ficha</button>`:''}
                    </td>
                  </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
    return;
  }

  // ── ADMIN / RECEPCIÓN ─────────────────────────────────────
  c.innerHTML = `
    <div class="stats-grid">
      <div class="stat-card accent-sage">
        <div class="stat-label">Citas hoy</div>
        <div class="stat-value">${d.citas_hoy ?? 0}</div>
        <div class="stat-sub">servicios agendados</div>
      </div>
      <div class="stat-card accent-terra">
        <div class="stat-label">Ingresos hoy</div>
        <div class="stat-value">${formatMoneda(d.ingresos_hoy)}</div>
        <div class="stat-sub">facturas pagadas</div>
      </div>
      <div class="stat-card accent-sand">
        <div class="stat-label">Ingresos del mes</div>
        <div class="stat-value">${formatMoneda(d.ingresos_mes)}</div>
        <div class="stat-sub">mes actual</div>
      </div>
      <div class="stat-card accent-dark">
        <div class="stat-label">Clientes / Mascotas</div>
        <div class="stat-value">${d.total_clientes ?? 0}</div>
        <div class="stat-sub">${d.total_mascotas ?? 0} mascotas registradas</div>
      </div>
    </div>
    ${(d.productos_bajo_stock ?? 0) > 0 ? `
      <div class="alert alert-warning mb-24">
        ⚠️ <strong>${d.productos_bajo_stock} producto(s)</strong> con stock bajo.
        <a href="#" onclick="window.navigate('productos','Productos')" style="color:inherit;font-weight:600;margin-left:8px">Ver inventario →</a>
      </div>` : ''}
    <div class="card">
      <div class="card-header">
        <span class="card-title">Próximas citas (7 días)</span>
        <button class="btn btn-primary btn-sm" onclick="window.navigate('citas','Citas')">Ver todas</button>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Mascota</th><th>Groomer</th><th>Servicio</th><th>Fecha</th><th>Estado</th></tr></thead>
          <tbody>
            ${!d.proximas_citas?.length
              ? '<tr><td colspan="6" style="text-align:center;color:var(--gray-light);padding:40px">Sin citas próximas</td></tr>'
              : d.proximas_citas.map(ci => `<tr>
                  <td class="text-muted text-small">#${ci.id}</td>
                  <td><strong>${ci.mascota}</strong></td>
                  <td>${ci.groomer}</td><td>${ci.servicio}</td>
                  <td>${formatFecha(ci.fecha_hora_inicio)}</td>
                  <td>${badgeEstadoCita(ci.estado)}</td>
                </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>`;
});

// ══════════════════════════════════════════════════════════════
// CITAS
// ══════════════════════════════════════════════════════════════
// ══════════════════════════════════════════════════════════════
// CITAS — corregido para todos los roles
// ══════════════════════════════════════════════════════════════
Router.register('citas', async () => {
  const rol     = Auth.user()?.rol;
  const esAdmin = rol === 'admin' || rol === 'recepcion';

  // /clientes solo para admin y recepción — cliente ve sus propias citas
  const [citasRes, groomersRes, serviciosRes] = await Promise.all([
    Http.get('/citas'),
    Http.get('/groomers'),
    Http.get('/servicios'),
  ]);
  const clientesRes = esAdmin ? await Http.get('/clientes') : { data: [] };

  window._citas     = citasRes?.data    || [];
  window._groomers  = groomersRes?.data || [];
  window._servicios = serviciosRes?.data || [];
  window._clientes  = clientesRes?.data  || [];
  window._slotSeleccionado = null;

  // Para clientes: cargar sus propias mascotas directamente
  let misMascotas = [];
  if (rol === 'cliente') {
    const mascRes = await Http.get('/mascotas');
    misMascotas = mascRes?.data || [];
  }
  window._misMascotas = misMascotas;

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="tabs" id="citas-tabs">
        <button class="tab-btn active" onclick="filtrarCitas('todas',this)">Todas</button>
        <button class="tab-btn" onclick="filtrarCitas('agendada',this)">Agendadas</button>
        <button class="tab-btn" onclick="filtrarCitas('confirmada',this)">Confirmadas</button>
        <button class="tab-btn" onclick="filtrarCitas('en_progreso',this)">En progreso</button>
        <button class="tab-btn" onclick="filtrarCitas('completada',this)">Completadas</button>
        <button class="tab-btn" onclick="filtrarCitas('cancelada',this)">Canceladas</button>
      </div>
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="date" class="form-control" id="filtro-fecha" style="width:160px" onchange="filtrarCitasPorFecha()">
        ${rol !== 'groomer' ? '<button class="btn btn-primary" onclick="openModal(\'modal-cita\')">+ Nueva cita</button>' : ''}
      </div>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Cliente</th><th>Mascota</th><th>Groomer</th><th>Servicio</th><th>Fecha / Hora</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody id="tbody-citas">${renderFilaCitas(window._citas)}</tbody>
        </table>
      </div>
    </div>

    <!-- Modal nueva cita -->
    <div class="modal-overlay" id="modal-cita">
      <div class="modal" style="max-width:620px">
        <div class="modal-header">
          <span class="modal-title">Nueva cita</span>
          <button class="modal-close" type="button" onclick="closeModal('modal-cita')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-cita"></div>

          ${esAdmin ? `
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label" for="cita-cliente">Cliente *</label>
              <select class="form-control" id="cita-cliente" name="cliente_id" onchange="cargarMascotasCliente()">
                <option value="">Seleccionar cliente...</option>
                ${window._clientes.map(c => `<option value="${c.id}">${c.nombre} ${c.telefono ? '— '+c.telefono : ''}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="cita-mascota">Mascota *</label>
              <select class="form-control" id="cita-mascota" name="mascota_id">
                <option value="">Selecciona un cliente primero</option>
              </select>
            </div>
          </div>` : `
          <div class="form-group">
            <label class="form-label" for="cita-mascota">Mi mascota *</label>
            <select class="form-control" id="cita-mascota" name="mascota_id">
              <option value="">Seleccionar mascota...</option>
              ${misMascotas.map(m => `<option value="${m.id}">${m.nombre} (${m.raza||m.especie||'—'})</option>`).join('')}
            </select>
          </div>`}

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label" for="cita-groomer">Groomer *</label>
              <select class="form-control" id="cita-groomer" name="groomer_id" onchange="cargarSlots()">
                <option value="">Seleccionar groomer...</option>
                ${window._groomers.map(g => `<option value="${g.id}">${g.nombre}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="cita-servicio">Servicio *</label>
              <select class="form-control" id="cita-servicio" name="servicio_id" onchange="cargarSlots()">
                <option value="">Seleccionar servicio...</option>
                ${window._servicios.map(s => `<option value="${s.id}">${s.nombre} — ${formatMoneda(s.precio_base)} (${s.duracion_base_minutos} min)</option>`).join('')}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="cita-fecha">Fecha *</label>
            <input type="date" class="form-control" id="cita-fecha" name="fecha"
              min="${new Date().toISOString().split('T')[0]}" onchange="cargarSlots()">
          </div>
          <div id="slots-wrap" style="display:none" class="form-group">
            <label class="form-label">Horarios disponibles</label>
            <div class="slots-grid" id="slots-grid"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="cita-notas">Notas adicionales</label>
            <textarea class="form-control" id="cita-notas" name="notas" rows="2" placeholder="Indicaciones especiales..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" onclick="closeModal('modal-cita')">Cancelar</button>
          <button class="btn btn-primary" type="button" id="btn-guardar-cita" onclick="guardarCita()">Agendar cita</button>
        </div>
      </div>
    </div>`;
});

function renderFilaCitas(citas) {
  if (!citas.length) return '<tr><td colspan="8" style="text-align:center;color:var(--gray-light);padding:40px">Sin citas registradas</td></tr>';
  const rol = Auth.user()?.rol;
  return citas.map(c => `
    <tr>
      <td class="text-muted text-small">#${c.id}</td>
      <td>${c.cliente_nombre||'—'}</td>
      <td><strong>${c.mascota_nombre||'—'}</strong><br><span class="text-muted text-small">${c.raza||''}</span></td>
      <td>${c.groomer_nombre||'—'}</td>
      <td>${c.servicio_nombre||'—'}</td>
      <td>${formatFecha(c.fecha_hora_inicio)}</td>
      <td>${badgeEstadoCita(c.estado)}</td>
      <td>
        <div class="flex gap-8" style="flex-wrap:wrap">
          ${c.estado==='agendada' && rol!=='groomer'    ? `<button class="btn btn-secondary btn-sm" type="button" onclick="cambiarEstadoCita(${c.id},'confirmada')">✓ Confirmar</button>` : ''}
          ${c.estado==='confirmada'                      ? `<button class="btn btn-secondary btn-sm" type="button" onclick="cambiarEstadoCita(${c.id},'en_progreso')">▶ Iniciar</button>` : ''}
          ${c.estado==='en_progreso'                     ? `<button class="btn btn-primary btn-sm"    type="button" onclick="window.navigate('fichas','Fichas')">📋 Ficha</button>` : ''}
          ${['agendada','confirmada'].includes(c.estado) && rol!=='groomer'
            ? `<button class="btn btn-secondary btn-sm" type="button" onclick="abrirReprogramar(${c.id})">📅 Reprog.</button>` : ''}
          ${['agendada','confirmada'].includes(c.estado)
            ? `<button class="btn btn-danger btn-sm" type="button" onclick="cambiarEstadoCita(${c.id},'cancelada')">✕</button>` : ''}
        </div>
      </td>
    </tr>`).join('');
}

window._filtroTab   = 'todas';
window._filtroFecha = '';

function filtrarCitas(estado, btn) {
  document.querySelectorAll('#citas-tabs .tab-btn').forEach(b => b.classList.remove('active'));
  btn?.classList.add('active');
  window._filtroTab = estado;
  aplicarFiltrosCitas();
}

function filtrarCitasPorFecha() {
  window._filtroFecha = document.getElementById('filtro-fecha').value;
  aplicarFiltrosCitas();
}

function aplicarFiltrosCitas() {
  let f = window._citas;
  if (window._filtroTab !== 'todas') f = f.filter(c => c.estado === window._filtroTab);
  if (window._filtroFecha) f = f.filter(c => c.fecha_hora_inicio?.startsWith(window._filtroFecha));
  document.getElementById('tbody-citas').innerHTML = renderFilaCitas(f);
}

async function cargarMascotasCliente() {
  const clienteId = document.getElementById('cita-cliente')?.value;
  const sel = document.getElementById('cita-mascota');
  if (!clienteId) { sel.innerHTML = '<option value="">Selecciona un cliente primero</option>'; return; }
  sel.innerHTML = '<option value="">Cargando...</option>';
  const res = await Http.get('/clientes/' + clienteId);
  const mascotas = res?.data?.mascotas || [];
  sel.innerHTML = mascotas.length
    ? '<option value="">Seleccionar mascota...</option>' + mascotas.map(m => `<option value="${m.id}">${m.nombre} (${m.raza||m.especie||'—'})</option>`).join('')
    : '<option value="">Este cliente no tiene mascotas</option>';
}

async function cargarSlots() {
  const gid   = document.getElementById('cita-groomer')?.value;
  const fecha = document.getElementById('cita-fecha')?.value;
  const srvId = document.getElementById('cita-servicio')?.value;
  if (!gid || !fecha) return;
  const wrap = document.getElementById('slots-wrap');
  const grid = document.getElementById('slots-grid');
  wrap.style.display = 'block';
  grid.innerHTML = '<span class="text-muted text-small">Cargando horarios...</span>';
  const res = await Http.get(`/disponibilidad?groomer_id=${gid}&fecha=${fecha}${srvId?'&servicio_id='+srvId:''}`);
  window._slotSeleccionado = null;
  if (!res?.data?.slots?.length) {
    grid.innerHTML = '<p class="text-muted text-small" style="margin:0">Sin horarios disponibles. Prueba otro día o groomer.</p>';
    return;
  }
  grid.innerHTML = res.data.slots.map(slot =>
    `<button class="slot-btn" type="button" onclick="seleccionarSlot('${fecha} ${slot}:00', this)">${slot}</button>`
  ).join('');
}

function seleccionarSlot(datetime, btn) {
  document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  window._slotSeleccionado = datetime;
}

async function guardarCita() {
  const rol    = Auth.user()?.rol;
  const esAdmin = rol === 'admin' || rol === 'recepcion';
  const mascotaId = parseInt(document.getElementById('cita-mascota')?.value || 0);
  const groId     = parseInt(document.getElementById('cita-groomer')?.value  || 0);
  const srvId     = parseInt(document.getElementById('cita-servicio')?.value || 0);
  const msgEl     = document.getElementById('msg-cita');
  const btn       = document.getElementById('btn-guardar-cita');

  // Para cliente, cliente_id lo resuelve el backend por su JWT
  const clienteId = esAdmin ? parseInt(document.getElementById('cita-cliente')?.value || 0) : undefined;

  const errores = [];
  if (esAdmin && !clienteId) errores.push('Selecciona un cliente');
  if (!mascotaId) errores.push('Selecciona una mascota');
  if (!groId)     errores.push('Selecciona un groomer');
  if (!srvId)     errores.push('Selecciona un servicio');
  if (!window._slotSeleccionado) errores.push('Selecciona un horario');

  if (errores.length) {
    msgEl.innerHTML = '<div class="alert alert-error">' + errores.join(' · ') + '</div>';
    return;
  }

  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> Agendando...';

  const body = {
    mascota_id:        mascotaId,
    groomer_id:        groId,
    servicio_id:       srvId,
    fecha_hora_inicio: window._slotSeleccionado,
    notas:             document.getElementById('cita-notas')?.value || '',
  };
  if (clienteId) body.cliente_id = clienteId;

  const res = await Http.post('/citas', body);
  btn.disabled = false; btn.innerHTML = 'Agendar cita';

  if (res?.success) {
    Toast.success('Cita agendada correctamente');
    closeModal('modal-cita');
    Router.go('citas');
  } else {
    msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message || 'Error al agendar') + '</div>';
  }
}

async function cambiarEstadoCita(id, estado) {
  const textos = {
    confirmada:  '¿Confirmar cita #' + id + '?',
    en_progreso: '¿Iniciar atención de la cita #' + id + '?',
    cancelada:   '¿Cancelar la cita #' + id + '? No se puede deshacer.',
  };
  if (!confirm(textos[estado] || '¿Cambiar estado de cita #' + id + '?')) return;
  const res = await Http.put('/citas/' + id + '/estado', { estado });
  if (res?.success) { Toast.success('Estado actualizado'); Router.go('citas'); }
  else Toast.error(res?.message || 'Error al cambiar estado');
}

function abrirReprogramar(id) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:440px">
      <div class="modal-header">
        <span class="modal-title">Reprogramar cita #${id}</span>
        <button class="modal-close" type="button" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        <div id="msg-reprog"></div>
        <div class="form-group">
          <label class="form-label" for="rep-fecha-${id}">Nueva fecha *</label>
          <input type="date" class="form-control" id="rep-fecha-${id}" name="rep_fecha" min="${new Date().toISOString().split('T')[0]}">
        </div>
        <div class="form-group">
          <label class="form-label" for="rep-hora-${id}">Nueva hora *</label>
          <input type="time" class="form-control" id="rep-hora-${id}" name="rep_hora" step="900">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" onclick="this.closest('.modal-overlay').remove()">Cancelar</button>
        <button class="btn btn-primary" type="button" onclick="confirmarReprogramar(${id}, '${id}', this)">Reprogramar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function confirmarReprogramar(id, suffix, btn) {
  const fecha = document.getElementById('rep-fecha-' + suffix)?.value;
  const hora  = document.getElementById('rep-hora-'  + suffix)?.value;
  const msgEl = document.getElementById('msg-reprog');
  if (!fecha || !hora) { msgEl.innerHTML = '<div class="alert alert-error">Fecha y hora son requeridas</div>'; return; }
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div>';
  const res = await Http.put('/citas/' + id + '/reprogramar', { fecha_hora_inicio: fecha + ' ' + hora + ':00' });
  btn.disabled = false; btn.innerHTML = 'Reprogramar';
  if (res?.success) { Toast.success('Cita reprogramada'); btn.closest('.modal-overlay').remove(); Router.go('citas'); }
  else { msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message || 'Error') + '</div>'; }
}
Router.register('fichas', async () => {
  const c = document.getElementById('page-content');
  const res = await Http.get('/citas');
  const enProgreso = (res?.data || []).filter(ci => ci.estado === 'en_progreso');
  window._checks = {};

  c.innerHTML = `
    <div class="card">
      <div class="card-header">
        <span class="card-title">Fichas de Grooming activas</span>
        <span class="badge badge-info">${enProgreso.length} en progreso</span>
      </div>
      <div class="card-body">
        ${!enProgreso.length
          ? '<p class="text-muted" style="text-align:center;padding:32px">No hay fichas activas en este momento. Las fichas se crean al iniciar una cita.</p>'
          : enProgreso.map(ci => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-bottom:1px solid var(--cream-dark)">
              <div>
                <strong style="font-size:1rem">${ci.mascota_nombre}</strong>
                <span class="badge badge-gray" style="margin-left:8px">${ci.raza||''}</span><br>
                <span class="text-muted text-small">Servicio: ${ci.servicio_nombre} · Groomer: ${ci.groomer_nombre}</span><br>
                <span class="text-muted text-small">${formatFecha(ci.fecha_hora_inicio)}</span>
              </div>
              <button class="btn btn-primary" onclick="abrirFicha(${ci.id},'${(ci.mascota_nombre||'').replace(/'/g,"\\'")}','${(ci.servicio_nombre||'').replace(/'/g,"\\'")}')">
                📋 Abrir ficha
              </button>
            </div>`).join('')}
      </div>
    </div>

    <div class="modal-overlay" id="modal-ficha">
      <div class="modal" style="max-width:680px">
        <div class="modal-header">
          <span class="modal-title" id="ficha-modal-title">Ficha de Grooming</span>
          <button class="modal-close" onclick="closeModal('modal-ficha')">×</button>
        </div>
        <div class="modal-body" id="ficha-modal-body"></div>
        <div class="modal-footer" id="ficha-modal-footer"></div>
      </div>
    </div>`;
});

async function abrirFicha(citaId, mascotaNombre, servicioNombre) {
  document.getElementById('ficha-modal-title').textContent = `Ficha: ${mascotaNombre} — ${servicioNombre}`;
  window._fichaActual = { citaId, checkCount: 0 };
  window._checks = {};

  const items = ['Baño', 'Corte de pelo', 'Corte de uñas', 'Limpieza de oídos', 'Glándulas anales', 'Perfume'];

  document.getElementById('ficha-modal-body').innerHTML = `
    <div id="msg-ficha"></div>
    <div class="grid-2 mb-16">
      <div class="form-group">
        <label class="form-label">Estado de ingreso del animal</label>
        <textarea class="form-control" id="ficha-estado-ini" rows="2" placeholder="Pelaje, nódulos, heridas observadas..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Temperatura corporal (°C)</label>
        <input type="number" class="form-control" id="ficha-temp" placeholder="38.5" step="0.1" min="35" max="42">
      </div>
    </div>
    <div class="form-group mb-16">
      <label class="form-label">Notas internas del equipo</label>
      <textarea class="form-control" id="ficha-notas" rows="2" placeholder="Observaciones solo visibles para el personal..."></textarea>
    </div>
    <div class="mb-16">
      <label class="form-label">Checklist de servicios <span class="text-muted" id="check-count">(0/${items.length} completados)</span></label>
      ${items.map((item, i) => `
        <div class="checklist-item" id="cli-${i}">
          <div class="check-box" id="cb-${i}" onclick="toggleCheck(${i}, ${items.length})"></div>
          <div style="flex:1">
            <span class="checklist-label">${item}</span>
            ${['Corte de uñas','Limpieza de oídos','Glándulas anales'].includes(item)
              ? `<input type="text" class="form-control" id="obs-${i}" placeholder="Observación..." style="margin-top:6px;font-size:.82rem" onclick="event.stopPropagation()">`
              : ''}
          </div>
        </div>`).join('')}
    </div>
    <div class="grid-2">
      <div>
        <label class="form-label">📷 Foto ANTES</label>
        <div class="foto-drop" onclick="document.getElementById('foto-antes').click()">
          <div class="foto-drop-icon">🐶</div>
          <div class="foto-drop-label">Clic para subir</div>
          <input type="file" id="foto-antes" accept="image/*" style="display:none" onchange="previewFoto(this,'antes')">
        </div>
        <div id="preview-antes" class="mt-8"></div>
      </div>
      <div>
        <label class="form-label">📷 Foto DESPUÉS</label>
        <div class="foto-drop" onclick="document.getElementById('foto-despues').click()">
          <div class="foto-drop-icon">✨</div>
          <div class="foto-drop-label">Clic para subir</div>
          <input type="file" id="foto-despues" accept="image/*" style="display:none" onchange="previewFoto(this,'despues')">
        </div>
        <div id="preview-despues" class="mt-8"></div>
      </div>
    </div>`;

  document.getElementById('ficha-modal-footer').innerHTML = `
    <button class="btn btn-secondary" onclick="closeModal('modal-ficha')">Cancelar</button>
    <button class="btn btn-secondary" onclick="guardarFichaParcial(${citaId})">💾 Guardar avance</button>
    <button class="btn btn-primary" id="btn-cerrar-ficha" onclick="cerrarFicha(${citaId})">✅ Cerrar servicio</button>`;

  openModal('modal-ficha');
}

function previewFoto(input, tipo) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('preview-' + tipo).innerHTML =
      `<img src="${e.target.result}" style="width:100%;border-radius:8px;max-height:150px;object-fit:cover;border:2px solid var(--sage-light)">`;
  };
  reader.readAsDataURL(file);
}

function toggleCheck(i, total) {
  window._checks[i] = !window._checks[i];
  const cb  = document.getElementById('cb-' + i);
  const row = document.getElementById('cli-' + i);
  cb.classList.toggle('checked', window._checks[i]);
  cb.innerHTML = window._checks[i] ? '✓' : '';
  row.classList.toggle('done', window._checks[i]);
  const count = Object.values(window._checks).filter(Boolean).length;
  document.getElementById('check-count').textContent = `(${count}/${total} completados)`;
}

async function guardarFichaParcial(citaId) {
  Toast.success('Avance guardado localmente');
}

async function cerrarFicha(citaId) {
  const checkCount = Object.values(window._checks).filter(Boolean).length;
  if (checkCount < 5) {
    document.getElementById('msg-ficha').innerHTML =
      '<div class="alert alert-error">Debes completar al menos 5 ítems del checklist para cerrar el servicio.</div>';
    return;
  }

  const fotoAntes   = document.getElementById('foto-antes').files[0];
  const fotoDespues = document.getElementById('foto-despues').files[0];
  if (!fotoAntes || !fotoDespues) {
    document.getElementById('msg-ficha').innerHTML =
      '<div class="alert alert-error">Debes subir al menos una foto "antes" y una foto "después".</div>';
    return;
  }

  const btn = document.getElementById('btn-cerrar-ficha');
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> Cerrando...';

  // Cambiar estado de la cita a completada
  const res = await Http.put('/citas/' + citaId + '/estado', { estado: 'completada' });

  btn.disabled = false; btn.innerHTML = '✅ Cerrar servicio';

  if (res?.success) {
    Toast.success('Servicio completado y ficha cerrada');
    closeModal('modal-ficha');
    Router.go('fichas');
  } else {
    document.getElementById('msg-ficha').innerHTML =
      '<div class="alert alert-error">' + (res?.message || 'Error al cerrar') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// CLIENTES
// ══════════════════════════════════════════════════════════════
Router.register('clientes', async () => {
  const res = await Http.get('/clientes');
  window._clientes = res?.data || [];
  window._clientesFiltrados = window._clientes;

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="search" class="form-control" placeholder="🔍 Nombre, teléfono, CI o email..."
          style="min-width:260px" oninput="filtrarClientes(this.value)">
        <select class="form-control" style="width:auto" onchange="filtrarClientesPorEstado(this.value)">
          <option value="">Todos los estados</option>
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>
      </div>
      <span class="text-muted text-small" id="clientes-count">${window._clientes.length} clientes</span>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>Cliente</th><th>Email</th><th>Teléfono</th><th>CI</th><th>Canal notif.</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody id="tbody-clientes">${renderFilaClientes(window._clientes)}</tbody>
        </table>
      </div>
    </div>`;
});

function renderFilaClientes(lista) {
  if (!lista.length) return '<tr><td colspan="7" style="text-align:center;color:var(--gray-light);padding:40px">Sin clientes</td></tr>';
  return lista.map(c => `
    <tr>
      <td>
        <div class="flex gap-12" style="align-items:center">
          <div class="user-avatar" style="background:var(--sage);flex-shrink:0">${iniciales(c.nombre)}</div>
          <div>
            <strong>${c.nombre}</strong>
            ${c.direccion ? `<div class="text-muted text-small">${c.direccion}</div>` : ''}
          </div>
        </div>
      </td>
      <td class="text-muted text-small">${c.email||'—'}</td>
      <td>${c.telefono||'—'}</td>
      <td>${c.ci||'—'}</td>
      <td><span class="badge badge-gray">${c.canal_notif||'email'}</span></td>
      <td>${c.cuenta_activa ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</td>
      <td>
        <div class="flex gap-8">
          <button class="btn btn-secondary btn-sm" onclick="verCliente(${c.id},'${(c.nombre||'').replace(/'/g,"\\'")}')">📋 Historial</button>
          <button class="btn btn-secondary btn-sm" onclick="editarCliente(${c.id})">✏️</button>
        </div>
      </td>
    </tr>`).join('');
}

function filtrarClientes(q) {
  const ql = q.toLowerCase();
  const f = window._clientes.filter(c =>
    (c.nombre||'').toLowerCase().includes(ql) ||
    (c.telefono||'').includes(q) ||
    (c.ci||'').includes(q) ||
    (c.email||'').toLowerCase().includes(ql));
  window._clientesFiltrados = f;
  document.getElementById('tbody-clientes').innerHTML = renderFilaClientes(f);
  document.getElementById('clientes-count').textContent = f.length + ' clientes';
}

function filtrarClientesPorEstado(val) {
  const f = val === '' ? window._clientes : window._clientes.filter(c => String(c.cuenta_activa?1:0) === val);
  document.getElementById('tbody-clientes').innerHTML = renderFilaClientes(f);
  document.getElementById('clientes-count').textContent = f.length + ' clientes';
}

async function verCliente(id, nombre) {
  const res = await Http.get('/clientes/' + id + '/historial');
  const hist = res?.data || [];
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:600px">
      <div class="modal-header">
        <span class="modal-title">Historial — ${nombre}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body" style="max-height:70vh;overflow-y:auto">
        ${!hist.length ? '<p class="text-muted" style="text-align:center;padding:32px">Sin historial de citas.</p>' :
          hist.map(h => `
            <div style="padding:12px 0;border-bottom:1px solid var(--cream-dark)">
              <div class="flex-between">
                <div>
                  <strong>${h.mascota||'—'}</strong>
                  <span class="text-muted" style="margin-left:8px">→ ${h.servicio||'—'}</span>
                </div>
                ${badgeEstadoCita(h.estado)}
              </div>
              <div class="text-muted text-small mt-8">
                📅 ${formatFecha(h.fecha_hora_inicio)} · 👤 ${h.groomer||'—'} · ${formatMoneda(h.precio_base)}
              </div>
            </div>`).join('')}
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function editarCliente(id) {
  const res = await Http.get('/clientes/' + id);
  if (!res?.success) { Toast.error('Error al cargar cliente'); return; }
  const c = res.data;
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:480px">
      <div class="modal-header">
        <span class="modal-title">Editar cliente — ${c.nombre}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        <div id="msg-edit-cliente"></div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre</label>
            <input class="form-control" id="ec-nombre" value="${c.nombre||''}">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input class="form-control" id="ec-telefono" value="${c.telefono||''}">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">CI</label>
            <input class="form-control" id="ec-ci" value="${c.ci||''}">
          </div>
          <div class="form-group">
            <label class="form-label">Canal de notificación</label>
            <select class="form-control" id="ec-canal">
              <option value="email" ${c.canal_notif==='email'?'selected':''}>Email</option>
              <option value="whatsapp" ${c.canal_notif==='whatsapp'?'selected':''}>WhatsApp</option>
              <option value="sms" ${c.canal_notif==='sms'?'selected':''}>SMS</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Dirección</label>
          <input class="form-control" id="ec-direccion" value="${c.direccion||''}">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarCliente(${id}, this)">Guardar cambios</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function guardarCliente(id, btn) {
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div>';
  const res = await Http.put('/clientes/' + id, {
    nombre:     document.getElementById('ec-nombre').value.trim(),
    telefono:   document.getElementById('ec-telefono').value.trim(),
    ci:         document.getElementById('ec-ci').value.trim(),
    direccion:  document.getElementById('ec-direccion').value.trim(),
    canal_notif:document.getElementById('ec-canal').value,
  });
  btn.disabled = false; btn.innerHTML = 'Guardar cambios';
  if (res?.success) { Toast.success('Cliente actualizado'); btn.closest('.modal-overlay').remove(); Router.go('clientes'); }
  else { document.getElementById('msg-edit-cliente').innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>'; }
}

// ══════════════════════════════════════════════════════════════
// MASCOTAS
// ══════════════════════════════════════════════════════════════
Router.register('mascotas', async () => {
  const [mascRes, clientesRes] = await Promise.all([Http.get('/mascotas'), Http.get('/clientes')]);
  window._mascotas = mascRes?.data || [];
  window._clientes = clientesRes?.data || [];
  const tempIcons  = { tranquilo:'😊', jugueton:'🎾', agresivo:'⚠️' };
  window._tempIcons = tempIcons;

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="search" class="form-control" placeholder="🔍 Nombre, raza o especie..."
          style="min-width:240px" oninput="filtrarMascotas(this.value)">
        <select class="form-control" style="width:auto" onchange="filtrarMascotasPorEspecie(this.value)">
          <option value="">Todas las especies</option>
          <option value="Perro">Perros</option>
          <option value="Gato">Gatos</option>
          <option value="Otro">Otros</option>
        </select>
      </div>
      <button class="btn btn-primary" onclick="openModal('modal-mascota')">+ Agregar mascota</button>
    </div>
    <div id="mascotas-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
      ${renderCardsMascotas(window._mascotas)}
    </div>

    <div class="modal-overlay" id="modal-mascota">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Registrar mascota</span>
          <button class="modal-close" onclick="closeModal('modal-mascota')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-mascota"></div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Nombre *</label>
              <input class="form-control" id="m-nombre" placeholder="Firulais">
            </div>
            <div class="form-group">
              <label class="form-label">Especie</label>
              <select class="form-control" id="m-especie">
                <option>Perro</option><option>Gato</option><option>Otro</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Raza</label>
              <input class="form-control" id="m-raza" placeholder="Labrador">
            </div>
            <div class="form-group">
              <label class="form-label">Peso (kg)</label>
              <input class="form-control" type="number" id="m-peso" placeholder="15.5" step="0.1" min="0">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Fecha de nacimiento</label>
              <input class="form-control" type="date" id="m-nacimiento">
            </div>
            <div class="form-group">
              <label class="form-label">Temperamento</label>
              <select class="form-control" id="m-temperamento">
                <option value="tranquilo">😊 Tranquilo</option>
                <option value="jugueton">🎾 Juguetón</option>
                <option value="agresivo">⚠️ Agresivo</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Alergias conocidas</label>
            <input class="form-control" id="m-alergias" placeholder="Ninguna / pollo / látex...">
          </div>
          <div class="form-group">
            <label class="form-label">Restricciones médicas</label>
            <textarea class="form-control" id="m-restricciones" rows="2" placeholder="Medicamentos, condiciones especiales..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Dueño (cliente) *</label>
            <select class="form-control" id="m-cliente-id">
              <option value="">Seleccionar cliente...</option>
              ${window._clientes.map(c => `<option value="${c.id}">${c.nombre} — ${c.telefono||c.email||''}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-mascota')">Cancelar</button>
          <button class="btn btn-primary" id="btn-guardar-mascota" onclick="guardarMascota()">Registrar mascota</button>
        </div>
      </div>
    </div>`;
});

function renderCardsMascotas(mascotas) {
  const ti = window._tempIcons || { tranquilo:'😊', jugueton:'🎾', agresivo:'⚠️' };
  if (!mascotas.length) return '<div class="text-muted text-small" style="grid-column:1/-1;text-align:center;padding:60px">Sin mascotas registradas</div>';
  return mascotas.map(m => `
    <div class="card" style="transition:transform .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
      <div style="background:linear-gradient(135deg,var(--cream-dark),var(--sand));padding:24px;text-align:center;border-radius:var(--radius-lg) var(--radius-lg) 0 0">
        <div style="font-size:3rem">${m.especie==='Gato'?'🐱':m.especie==='Otro'?'🐹':'🐶'}</div>
        <div style="font-family:var(--font-display);font-size:1.15rem;margin-top:8px;color:var(--charcoal)">${m.nombre}</div>
        <div class="text-muted text-small">${m.raza||m.especie||'—'} ${m.peso_kg ? '· '+m.peso_kg+' kg' : ''}</div>
      </div>
      <div style="padding:16px">
        <div class="flex gap-8 flex-wrap mb-8">
          ${m.temperamento ? `<span class="badge badge-gray">${ti[m.temperamento]||''} ${m.temperamento}</span>` : ''}
          ${m.alergias && m.alergias.toLowerCase() !== 'ninguna' ? `<span class="badge badge-terra">⚠ Alergias</span>` : ''}
          ${m.restricciones_medicas ? `<span class="badge badge-warning">💊 Restricciones</span>` : ''}
        </div>
        ${m.dueno_nombre ? `<div class="text-muted text-small">👤 ${m.dueno_nombre}</div>` : ''}
      </div>
    </div>`).join('');
}

function filtrarMascotas(q) {
  const ql = q.toLowerCase();
  const f = window._mascotas.filter(m =>
    (m.nombre||'').toLowerCase().includes(ql) ||
    (m.raza||'').toLowerCase().includes(ql) ||
    (m.especie||'').toLowerCase().includes(ql));
  document.getElementById('mascotas-grid').innerHTML = renderCardsMascotas(f);
}

function filtrarMascotasPorEspecie(especie) {
  const f = especie ? window._mascotas.filter(m => m.especie === especie) : window._mascotas;
  document.getElementById('mascotas-grid').innerHTML = renderCardsMascotas(f);
}

async function guardarMascota() {
  const clienteId = parseInt(document.getElementById('m-cliente-id').value);
  const nombre    = document.getElementById('m-nombre').value.trim();
  const msgEl     = document.getElementById('msg-mascota');
  const btn       = document.getElementById('btn-guardar-mascota');

  if (!nombre) { msgEl.innerHTML = '<div class="alert alert-error">El nombre es requerido</div>'; return; }
  if (!clienteId) { msgEl.innerHTML = '<div class="alert alert-error">Selecciona el dueño (cliente)</div>'; return; }

  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div> Registrando...';

  const res = await Http.post('/mascotas', {
    nombre, cliente_id: clienteId,
    especie:       document.getElementById('m-especie').value,
    raza:          document.getElementById('m-raza').value.trim(),
    peso_kg:       parseFloat(document.getElementById('m-peso').value) || null,
    fecha_nacimiento: document.getElementById('m-nacimiento').value || null,
    temperamento:  document.getElementById('m-temperamento').value,
    alergias:      document.getElementById('m-alergias').value.trim(),
    restricciones_medicas: document.getElementById('m-restricciones').value.trim(),
  });

  btn.disabled = false; btn.innerHTML = 'Registrar mascota';

  if (res?.success) {
    Toast.success('Mascota registrada correctamente');
    closeModal('modal-mascota');
    Router.go('mascotas');
  } else {
    msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message || 'Error al registrar') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// SERVICIOS
// ══════════════════════════════════════════════════════════════
Router.register('servicios', async () => {
  const res = await Http.get('/servicios');
  window._servicios = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <input type="search" class="form-control" placeholder="🔍 Buscar servicio..."
        style="max-width:280px" oninput="filtrarServicios(this.value)">
      <button class="btn btn-primary" onclick="openModal('modal-servicio')">+ Nuevo servicio</button>
    </div>
    <div id="servicios-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      ${renderCardsServicios(window._servicios)}
    </div>

    <div class="modal-overlay" id="modal-servicio">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Nuevo servicio</span>
          <button class="modal-close" onclick="closeModal('modal-servicio')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-servicio"></div>
          <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input class="form-control" id="s-nombre" placeholder="Baño completo">
          </div>
          <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" id="s-desc" rows="2" placeholder="Descripción del servicio..."></textarea>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Duración base (min) — múltiplo de 15</label>
              <input class="form-control" type="number" id="s-duracion" placeholder="60" step="15" min="15" max="360">
            </div>
            <div class="form-group">
              <label class="form-label">Precio base (Bs)</label>
              <input class="form-control" type="number" id="s-precio" placeholder="80.00" step="0.50" min="0">
            </div>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none">
              <input type="checkbox" id="s-doble"> Permite doble booking simultáneo
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-servicio')">Cancelar</button>
          <button class="btn btn-primary" id="btn-guardar-srv" onclick="guardarServicio()">Crear servicio</button>
        </div>
      </div>
    </div>`;
});

function renderCardsServicios(lista) {
  if (!lista.length) return '<div class="text-muted text-small" style="grid-column:1/-1;text-align:center;padding:60px">Sin servicios</div>';
  return lista.map(s => `
    <div class="card" style="border-top:3px solid var(--sage)">
      <div class="card-body">
        <div class="flex-between mb-12">
          <h3 class="text-serif" style="font-size:1.05rem">${s.nombre}</h3>
          <span class="badge badge-info">${s.duracion_base_minutos} min</span>
        </div>
        <p class="text-muted text-small mb-16" style="min-height:36px">${s.descripcion||'Sin descripción'}</p>
        <div class="flex-between">
          <span style="font-size:1.3rem;font-family:var(--font-display);color:var(--sage-dark)">${formatMoneda(s.precio_base)}</span>
          <div class="flex gap-8">
            ${s.permite_doble_booking ? '<span class="badge badge-gray">Doble</span>' : ''}
            <button class="btn btn-danger btn-sm" onclick="desactivarServicio(${s.id},'${s.nombre.replace(/'/g,"\\'")}')">✕</button>
          </div>
        </div>
      </div>
    </div>`).join('');
}

function filtrarServicios(q) {
  const ql = q.toLowerCase();
  const f = window._servicios.filter(s => (s.nombre||'').toLowerCase().includes(ql));
  document.getElementById('servicios-grid').innerHTML = renderCardsServicios(f);
}

async function guardarServicio() {
  const nombre = document.getElementById('s-nombre').value.trim();
  const dur    = parseInt(document.getElementById('s-duracion').value);
  const precio = parseFloat(document.getElementById('s-precio').value);
  const msgEl  = document.getElementById('msg-servicio');
  const btn    = document.getElementById('btn-guardar-srv');

  if (!nombre) { msgEl.innerHTML = '<div class="alert alert-error">El nombre es requerido</div>'; return; }
  if (!dur || dur % 15 !== 0) { msgEl.innerHTML = '<div class="alert alert-error">La duración debe ser múltiplo de 15 minutos</div>'; return; }
  if (isNaN(precio) || precio < 0) { msgEl.innerHTML = '<div class="alert alert-error">Precio inválido</div>'; return; }

  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div>';

  const res = await Http.post('/servicios', {
    nombre, precio_base: precio,
    duracion_base_minutos: dur,
    descripcion: document.getElementById('s-desc').value.trim(),
    permite_doble_booking: document.getElementById('s-doble').checked,
  });

  btn.disabled = false; btn.innerHTML = 'Crear servicio';

  if (res?.success) { Toast.success('Servicio creado'); closeModal('modal-servicio'); Router.go('servicios'); }
  else { msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>'; }
}

async function desactivarServicio(id, nombre) {
  if (!confirm(`¿Desactivar el servicio "${nombre}"? No aparecerá en nuevas citas.`)) return;
  const res = await Http.delete('/servicios/' + id);
  if (res?.success) { Toast.success('Servicio desactivado'); Router.go('servicios'); }
  else Toast.error(res?.message || 'Error');
}

// ══════════════════════════════════════════════════════════════
// PRODUCTOS
// ══════════════════════════════════════════════════════════════
Router.register('productos', async () => {
  const [prodRes, bajosRes] = await Promise.all([Http.get('/productos'), Http.get('/productos/bajo-stock')]);
  window._productos = prodRes?.data || [];
  const bajos = bajosRes?.data || [];

  document.getElementById('page-content').innerHTML = `
    ${bajos.length ? `<div class="alert alert-warning mb-16">
      ⚠️ <strong>Stock bajo:</strong> ${bajos.map(p => `${p.nombre} (${p.stock} und.)`).join(' · ')}
    </div>` : ''}
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="search" class="form-control" placeholder="🔍 Nombre, SKU..."
          style="min-width:240px" oninput="filtrarProductos(this.value)">
        <select class="form-control" style="width:auto" onchange="filtrarProductosPorCategoria(this.value)">
          <option value="">Todas las categorías</option>
          ${[...new Set((prodRes?.data||[]).map(p => p.categoria).filter(Boolean))].map(cat =>
            `<option value="${cat}">${cat}</option>`).join('')}
        </select>
      </div>
      <button class="btn btn-primary" onclick="openModal('modal-producto')">+ Nuevo producto</button>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>Producto</th><th>SKU</th><th>Precio</th><th>Stock</th><th>Stock mín.</th><th>Categoría</th><th>Acciones</th></tr>
          </thead>
          <tbody id="tbody-productos">${renderFilaProductos(window._productos)}</tbody>
        </table>
      </div>
    </div>

    <div class="modal-overlay" id="modal-producto">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Nuevo producto</span>
          <button class="modal-close" onclick="closeModal('modal-producto')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-producto"></div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Nombre *</label>
              <input class="form-control" id="p-nombre" placeholder="Shampoo neutro">
            </div>
            <div class="form-group">
              <label class="form-label">SKU *</label>
              <input class="form-control" id="p-sku" placeholder="SHP-004">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Precio base (Bs) *</label>
              <input class="form-control" type="number" id="p-precio" step="0.50" min="0" placeholder="35.00">
            </div>
            <div class="form-group">
              <label class="form-label">Stock inicial</label>
              <input class="form-control" type="number" id="p-stock" value="0" min="0">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Stock mínimo (alerta)</label>
              <input class="form-control" type="number" id="p-stock-min" value="5" min="0">
            </div>
            <div class="form-group">
              <label class="form-label">Descripción</label>
              <input class="form-control" id="p-desc" placeholder="Descripción breve...">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-producto')">Cancelar</button>
          <button class="btn btn-primary" id="btn-guardar-prod" onclick="guardarProducto()">Crear producto</button>
        </div>
      </div>
    </div>

    <!-- Modal ajuste de stock -->
    <div class="modal-overlay" id="modal-stock">
      <div class="modal" style="max-width:360px">
        <div class="modal-header">
          <span class="modal-title" id="stock-modal-title">Ajustar stock</span>
          <button class="modal-close" onclick="closeModal('modal-stock')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-stock"></div>
          <div class="form-group">
            <label class="form-label">Nuevo stock</label>
            <input type="number" class="form-control" id="nuevo-stock" min="0">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-stock')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarStock()">Actualizar</button>
        </div>
      </div>
    </div>`;
});

function renderFilaProductos(prods) {
  if (!prods.length) return '<tr><td colspan="7" style="text-align:center;color:var(--gray-light);padding:40px">Sin productos</td></tr>';
  return prods.map(p => `
    <tr>
      <td>
        <strong>${p.nombre}</strong>
        ${p.descripcion ? `<div class="text-muted text-small">${p.descripcion}</div>` : ''}
      </td>
      <td><code style="font-size:.78rem;background:var(--cream);padding:2px 6px;border-radius:4px">${p.sku}</code></td>
      <td>${formatMoneda(p.precio_base)}</td>
      <td>
        <span class="badge ${p.stock <= p.stock_minimo ? 'badge-terra' : 'badge-sage'}">${p.stock} und.</span>
      </td>
      <td class="text-muted text-small">${p.stock_minimo}</td>
      <td class="text-muted text-small">${p.categoria||'—'}</td>
      <td>
        <div class="flex gap-8">
          <button class="btn btn-secondary btn-sm" onclick="abrirAjusteStock(${p.id},'${p.nombre.replace(/'/g,"\\'")}',${p.stock})">📦 Stock</button>
        </div>
      </td>
    </tr>`).join('');
}

function filtrarProductos(q) {
  const ql = q.toLowerCase();
  const f = window._productos.filter(p => (p.nombre||'').toLowerCase().includes(ql) || (p.sku||'').includes(q));
  document.getElementById('tbody-productos').innerHTML = renderFilaProductos(f);
}

function filtrarProductosPorCategoria(cat) {
  const f = cat ? window._productos.filter(p => p.categoria === cat) : window._productos;
  document.getElementById('tbody-productos').innerHTML = renderFilaProductos(f);
}

async function guardarProducto() {
  const nombre = document.getElementById('p-nombre').value.trim();
  const sku    = document.getElementById('p-sku').value.trim();
  const precio = parseFloat(document.getElementById('p-precio').value);
  const msgEl  = document.getElementById('msg-producto');
  const btn    = document.getElementById('btn-guardar-prod');

  if (!nombre || !sku) { msgEl.innerHTML = '<div class="alert alert-error">Nombre y SKU son requeridos</div>'; return; }
  if (isNaN(precio) || precio < 0) { msgEl.innerHTML = '<div class="alert alert-error">Precio inválido</div>'; return; }

  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div>';

  const res = await Http.post('/productos', {
    nombre, sku, precio_base: precio,
    stock:        parseInt(document.getElementById('p-stock').value) || 0,
    stock_minimo: parseInt(document.getElementById('p-stock-min').value) || 5,
    descripcion:  document.getElementById('p-desc').value.trim(),
  });

  btn.disabled = false; btn.innerHTML = 'Crear producto';

  if (res?.success) { Toast.success('Producto creado'); closeModal('modal-producto'); Router.go('productos'); }
  else { msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>'; }
}

function abrirAjusteStock(id, nombre, stockActual) {
  window._stockProductoId = id;
  document.getElementById('stock-modal-title').textContent = `Stock: ${nombre}`;
  document.getElementById('nuevo-stock').value = stockActual;
  openModal('modal-stock');
}

async function guardarStock() {
  const nuevoStock = parseInt(document.getElementById('nuevo-stock').value);
  if (isNaN(nuevoStock) || nuevoStock < 0) { document.getElementById('msg-stock').innerHTML = '<div class="alert alert-error">Stock inválido</div>'; return; }
  const res = await Http.put('/productos/' + window._stockProductoId, { stock: nuevoStock });
  if (res?.success) { Toast.success('Stock actualizado'); closeModal('modal-stock'); Router.go('productos'); }
  else Toast.error(res?.message || 'Error');
}

// ══════════════════════════════════════════════════════════════
// FACTURAS
// ══════════════════════════════════════════════════════════════
Router.register('facturas', async () => {
  const res = await Http.get('/facturas');
  window._facturas = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="flex gap-8" style="flex-wrap:wrap">
        <select class="form-control" style="width:auto" onchange="filtrarFacturas(this.value)">
          <option value="">Todos los estados</option>
          <option value="pendiente">Pendiente</option>
          <option value="pagada">Pagada</option>
          <option value="cancelada">Cancelada</option>
        </select>
        <input type="date" class="form-control" style="width:170px" onchange="filtrarFacturasFecha(this.value)">
      </div>
      <div class="text-muted text-small">
        Total: ${formatMoneda(window._facturas.filter(f=>f.estado==='pagada').reduce((s,f)=>s+parseFloat(f.total||0),0))} pagados
      </div>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>#</th><th>Cliente</th><th>Subtotal</th><th>Total</th><th>Método</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
          </thead>
          <tbody id="tbody-facturas">${renderFilaFacturas(window._facturas)}</tbody>
        </table>
      </div>
    </div>

    <div class="modal-overlay" id="modal-pago">
      <div class="modal" style="max-width:400px">
        <div class="modal-header">
          <span class="modal-title" id="pago-modal-title">Registrar pago</span>
          <button class="modal-close" onclick="closeModal('modal-pago')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-pago"></div>
          <div class="form-group">
            <label class="form-label">Monto a pagar (Bs)</label>
            <input type="number" class="form-control" id="pago-monto" step="0.50" min="0.01">
          </div>
          <div class="form-group">
            <label class="form-label">Método de pago</label>
            <select class="form-control" id="pago-metodo">
              <option value="efectivo">💵 Efectivo</option>
              <option value="qr">📱 QR / Transferencia</option>
              <option value="tarjeta">💳 Tarjeta</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Referencia (opcional)</label>
            <input class="form-control" id="pago-ref" placeholder="Nro. de comprobante...">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-pago')">Cancelar</button>
          <button class="btn btn-primary" id="btn-confirmar-pago" onclick="confirmarPago()">✓ Confirmar pago</button>
        </div>
      </div>
    </div>`;
});

function renderFilaFacturas(lista) {
  if (!lista.length) return '<tr><td colspan="8" style="text-align:center;color:var(--gray-light);padding:40px">Sin facturas</td></tr>';
  const estadoClase = { pendiente:'badge-warning', pagada:'badge-sage', cancelada:'badge-terra' };
  return lista.map(f => `
    <tr>
      <td class="text-muted text-small">#${f.numero||f.id}</td>
      <td>${f.cliente_nombre||'—'}</td>
      <td class="text-muted text-small">${formatMoneda(f.subtotal)}</td>
      <td><strong>${formatMoneda(f.total)}</strong></td>
      <td>${f.metodo_pago||'—'}</td>
      <td><span class="badge ${estadoClase[f.estado]||'badge-gray'}">${f.estado}</span></td>
      <td class="text-small text-muted">${formatFecha(f.created_at)}</td>
      <td>
        ${f.estado==='pendiente' ? `<button class="btn btn-primary btn-sm" onclick="abrirModalPago(${f.id},${f.total})">💳 Cobrar</button>` : ''}
        <button class="btn btn-secondary btn-sm" onclick="verFactura(${f.id})">🧾 Ver</button>
      </td>
    </tr>`).join('');
}

function filtrarFacturas(estado) {
  const f = estado ? window._facturas.filter(f => f.estado === estado) : window._facturas;
  document.getElementById('tbody-facturas').innerHTML = renderFilaFacturas(f);
}

function filtrarFacturasFecha(fecha) {
  const f = fecha ? window._facturas.filter(f => f.created_at?.startsWith(fecha)) : window._facturas;
  document.getElementById('tbody-facturas').innerHTML = renderFilaFacturas(f);
}

function abrirModalPago(id, total) {
  window._facturaIdPago = id;
  document.getElementById('pago-modal-title').textContent = `Cobrar factura #${id} — Total: ${formatMoneda(total)}`;
  document.getElementById('pago-monto').value = total;
  document.getElementById('msg-pago').innerHTML = '';
  openModal('modal-pago');
}

async function confirmarPago() {
  const monto = parseFloat(document.getElementById('pago-monto').value);
  const metodo = document.getElementById('pago-metodo').value;
  const ref    = document.getElementById('pago-ref').value.trim();
  const btn    = document.getElementById('btn-confirmar-pago');
  if (!monto || monto <= 0) { document.getElementById('msg-pago').innerHTML = '<div class="alert alert-error">Monto inválido</div>'; return; }
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div>';
  const res = await Http.post('/facturas/' + window._facturaIdPago + '/pago', { monto, metodo, referencia: ref });
  btn.disabled = false; btn.innerHTML = '✓ Confirmar pago';
  if (res?.success) { Toast.success('Pago registrado'); closeModal('modal-pago'); Router.go('facturas'); }
  else { document.getElementById('msg-pago').innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>'; }
}

async function verFactura(id) {
  const res = await Http.get('/facturas/' + id);
  if (!res?.success) { Toast.error('Error al cargar factura'); return; }
  const f = res.data;
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:520px">
      <div class="modal-header">
        <span class="modal-title">Factura #${f.numero||f.id}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        <div class="flex-between mb-16">
          <div><span class="text-muted text-small">Cliente</span><br><strong>${f.cliente_nombre||'—'}</strong></div>
          <div><span class="text-muted text-small">Estado</span><br><span class="badge ${f.estado==='pagada'?'badge-sage':'badge-warning'}">${f.estado}</span></div>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.875rem">
          <thead><tr style="background:var(--cream)">
            <th style="padding:8px;text-align:left">Descripción</th>
            <th style="padding:8px;text-align:right">Cant.</th>
            <th style="padding:8px;text-align:right">Precio</th>
            <th style="padding:8px;text-align:right">Subtotal</th>
          </tr></thead>
          <tbody>
            ${(f.items||[]).map(i => `<tr>
              <td style="padding:8px;border-bottom:1px solid var(--cream-dark)">${i.descripcion}</td>
              <td style="padding:8px;text-align:right;border-bottom:1px solid var(--cream-dark)">${i.cantidad}</td>
              <td style="padding:8px;text-align:right;border-bottom:1px solid var(--cream-dark)">${formatMoneda(i.precio_unitario)}</td>
              <td style="padding:8px;text-align:right;border-bottom:1px solid var(--cream-dark)">${formatMoneda(i.subtotal)}</td>
            </tr>`).join('')}
          </tbody>
          <tfoot>
            <tr><td colspan="3" style="padding:8px;text-align:right;color:var(--gray)">Subtotal</td><td style="padding:8px;text-align:right">${formatMoneda(f.subtotal)}</td></tr>
            ${f.impuesto>0?`<tr><td colspan="3" style="padding:8px;text-align:right;color:var(--gray)">IVA 13%</td><td style="padding:8px;text-align:right">${formatMoneda(f.impuesto)}</td></tr>`:''}
            <tr style="background:var(--cream)"><td colspan="3" style="padding:8px;text-align:right;font-weight:600">TOTAL</td><td style="padding:8px;text-align:right;font-weight:600;font-size:1.1rem">${formatMoneda(f.total)}</td></tr>
          </tfoot>
        </table>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

// ══════════════════════════════════════════════════════════════
// GROOMERS
// ══════════════════════════════════════════════════════════════
Router.register('groomers', async () => {
  const res = await Http.get('/groomers');
  window._groomers = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16">
      <input type="search" class="form-control" placeholder="🔍 Buscar groomer..."
        style="max-width:280px" oninput="filtrarGroomers(this.value)">
    </div>
    <div id="groomers-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      ${renderCardsGroomers(window._groomers)}
    </div>

    <div class="modal-overlay" id="modal-disponibilidad">
      <div class="modal" style="max-width:560px">
        <div class="modal-header">
          <span class="modal-title" id="dispo-modal-title">Disponibilidad</span>
          <button class="modal-close" onclick="closeModal('modal-disponibilidad')">×</button>
        </div>
        <div class="modal-body" id="dispo-modal-body"></div>
      </div>
    </div>

    <div class="modal-overlay" id="modal-bloqueo">
      <div class="modal" style="max-width:440px">
        <div class="modal-header">
          <span class="modal-title">Registrar bloqueo</span>
          <button class="modal-close" onclick="closeModal('modal-bloqueo')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-bloqueo"></div>
          <div class="form-group">
            <label class="form-label">Tipo</label>
            <select class="form-control" id="bl-tipo">
              <option value="feriado">Feriado</option>
              <option value="vacaciones">Vacaciones</option>
              <option value="ausencia">Ausencia</option>
              <option value="mantenimiento">Mantenimiento</option>
            </select>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Desde *</label>
              <input type="datetime-local" class="form-control" id="bl-ini">
            </div>
            <div class="form-group">
              <label class="form-label">Hasta *</label>
              <input type="datetime-local" class="form-control" id="bl-fin">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Descripción</label>
            <input class="form-control" id="bl-desc" placeholder="Motivo del bloqueo...">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-bloqueo')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarBloqueo()">Guardar bloqueo</button>
        </div>
      </div>
    </div>`;
});

function renderCardsGroomers(lista) {
  if (!lista.length) return '<div class="text-muted text-small" style="grid-column:1/-1;text-align:center;padding:60px">Sin groomers</div>';
  return lista.map(g => `
    <div class="card">
      <div style="background:linear-gradient(135deg,var(--charcoal),#3d3d3d);padding:24px;border-radius:var(--radius-lg) var(--radius-lg) 0 0">
        <div class="flex gap-12" style="align-items:center">
          <div class="user-avatar" style="width:48px;height:48px;font-size:1.1rem;background:var(--sage)">${iniciales(g.nombre)}</div>
          <div>
            <div style="color:var(--cream);font-weight:600;font-size:1rem">${g.nombre}</div>
            <div style="color:var(--sage-light);font-size:.8rem">${g.especialidad||'Groomer'}</div>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="flex gap-8 flex-wrap mb-12">
          <span class="badge badge-info">Cap. ${g.capacidad_simultanea}</span>
          <span class="badge badge-gray">${g.turno||'—'}</span>
          ${g.estado_activo ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-terra">Inactivo</span>'}
        </div>
        <div class="text-muted text-small mb-12">${g.telefono||'Sin teléfono'} · ${g.email||''}</div>
        <div class="flex gap-8">
          <button class="btn btn-secondary btn-sm" onclick="verDisponibilidad(${g.id},'${(g.nombre||'').replace(/'/g,"\\'")}')">📅 Disponibilidad</button>
          <button class="btn btn-secondary btn-sm" onclick="abrirBloqueo(${g.id})">🚫 Bloquear</button>
        </div>
      </div>
    </div>`).join('');
}

function filtrarGroomers(q) {
  const ql = q.toLowerCase();
  const f = window._groomers.filter(g => (g.nombre||'').toLowerCase().includes(ql) || (g.especialidad||'').toLowerCase().includes(ql));
  document.getElementById('groomers-grid').innerHTML = renderCardsGroomers(f);
}

async function verDisponibilidad(id, nombre) {
  document.getElementById('dispo-modal-title').textContent = `Disponibilidad — ${nombre}`;
  document.getElementById('dispo-modal-body').innerHTML = '<div class="flex-center" style="padding:32px"><div class="spinner spinner-dark"></div></div>';
  openModal('modal-disponibilidad');

  const [dispoRes, bloqRes] = await Promise.all([
    Http.get('/groomers/' + id + '/disponibilidad'),
    Http.get('/groomers/' + id + '/bloqueos'),
  ]);
  const dias  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
  const dispo = dispoRes?.data || [];
  const bloqs = bloqRes?.data || [];

  document.getElementById('dispo-modal-body').innerHTML = `
    <div class="mb-24">
      <label class="form-label" style="margin-bottom:12px">Horario semanal</label>
      ${!dispo.length ? '<p class="text-muted">Sin horarios configurados.</p>' :
        dispo.map(d => `
          <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--cream-dark)">
            <strong style="width:40px">${dias[d.dia_semana]}</strong>
            <span>${d.hora_inicio} — ${d.hora_fin}</span>
            ${d.descanso ? `<span class="text-muted text-small">Almuerzo ${JSON.parse(d.descanso).inicio||''}-${JSON.parse(d.descanso).fin||''}</span>` : '<span></span>'}
            <span class="badge badge-gray">Buffer ${d.buffer_minutos}min</span>
          </div>`).join('')}
    </div>
    <div>
      <label class="form-label" style="margin-bottom:12px">Bloqueos activos</label>
      ${!bloqs.length ? '<p class="text-muted">Sin bloqueos.</p>' :
        bloqs.filter(b => new Date(b.fecha_fin) >= new Date()).map(b => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--cream-dark)">
            <div>
              <span class="badge badge-terra">${b.tipo}</span>
              <span class="text-small" style="margin-left:8px">${b.descripcion||''}</span>
            </div>
            <div class="text-muted text-small">${formatFecha(b.fecha_inicio)} → ${formatFecha(b.fecha_fin)}</div>
          </div>`).join('')}
    </div>`;
}

window._groomerBloqueoId = null;
function abrirBloqueo(groomerId) {
  window._groomerBloqueoId = groomerId;
  document.getElementById('msg-bloqueo').innerHTML = '';
  openModal('modal-bloqueo');
}

async function guardarBloqueo() {
  const ini  = document.getElementById('bl-ini').value;
  const fin  = document.getElementById('bl-fin').value;
  const tipo = document.getElementById('bl-tipo').value;
  if (!ini || !fin) { document.getElementById('msg-bloqueo').innerHTML = '<div class="alert alert-error">Fechas requeridas</div>'; return; }
  const res = await Http.post('/groomers/' + window._groomerBloqueoId + '/bloqueos', {
    tipo, fecha_inicio: ini, fecha_fin: fin,
    descripcion: document.getElementById('bl-desc').value,
  });
  if (res?.success) { Toast.success('Bloqueo registrado'); closeModal('modal-bloqueo'); }
  else { document.getElementById('msg-bloqueo').innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>'; }
}

// ══════════════════════════════════════════════════════════════
// USUARIOS (admin)
// ══════════════════════════════════════════════════════════════
Router.register('usuarios', async () => {
  const res = await Http.get('/admin/usuarios');
  window._usuarios = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="search" class="form-control" placeholder="🔍 Buscar email..."
          style="min-width:240px" oninput="filtrarUsuarios(this.value)">
        <select class="form-control" style="width:auto" onchange="filtrarUsuariosPorRol(this.value)">
          <option value="">Todos los roles</option>
          <option value="admin">Admin</option>
          <option value="recepcion">Recepción</option>
          <option value="groomer">Groomer</option>
          <option value="cliente">Cliente</option>
        </select>
      </div>
      <button class="btn btn-primary" onclick="openModal('modal-usuario')">+ Crear personal</button>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>Email</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr>
          </thead>
          <tbody id="tbody-usuarios">${renderFilaUsuarios(window._usuarios)}</tbody>
        </table>
      </div>
    </div>

    <div class="modal-overlay" id="modal-usuario">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Crear personal</span>
          <button class="modal-close" onclick="closeModal('modal-usuario')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-usuario"></div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input class="form-control" type="email" id="u-email" placeholder="groomer@petspa.bo">
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Nombre completo</label>
              <input class="form-control" id="u-nombre" placeholder="María López">
            </div>
            <div class="form-group">
              <label class="form-label">Teléfono</label>
              <input class="form-control" id="u-telefono" placeholder="70000000">
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Rol *</label>
              <select class="form-control" id="u-rol" onchange="toggleGroomerFields()">
                <option value="recepcion">Recepción</option>
                <option value="groomer">Groomer</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Contraseña temporal *</label>
              <input class="form-control" type="password" id="u-pass" placeholder="Mín. 8 chars, mayús., número, símbolo">
            </div>
          </div>
          <div id="groomer-extra">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Especialidad</label>
                <input class="form-control" id="u-especialidad" placeholder="Corte fino, razas pequeñas...">
              </div>
              <div class="form-group">
                <label class="form-label">Turno</label>
                <select class="form-control" id="u-turno">
                  <option value="mañana">Mañana</option>
                  <option value="tarde">Tarde</option>
                  <option value="completo">Completo</option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-usuario')">Cancelar</button>
          <button class="btn btn-primary" id="btn-guardar-usr" onclick="guardarUsuario()">Crear cuenta</button>
        </div>
      </div>
    </div>`;
});

function renderFilaUsuarios(lista) {
  if (!lista.length) return '<tr><td colspan="6" style="text-align:center;color:var(--gray-light);padding:40px">Sin usuarios</td></tr>';
  const rolClass = { admin:'badge-dark', recepcion:'badge-info', groomer:'badge-sage', cliente:'badge-gray' };
  return lista.map(u => `
    <tr>
      <td>${u.email}</td>
      <td>${u.nombre_perfil||'—'}</td>
      <td><span class="badge ${rolClass[u.rol]||'badge-gray'}">${u.rol}</span></td>
      <td>${u.estado ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</td>
      <td class="text-muted text-small">${u.ultimo_acceso ? formatFecha(u.ultimo_acceso) : 'Nunca'}</td>
      <td>
        <div class="flex gap-8" style="flex-wrap:wrap">
          <button class="btn btn-secondary btn-sm" onclick="verDetalleUsuario(${u.id})">👁 Ver</button>
          <button class="btn btn-secondary btn-sm" onclick="toggleUsuario(${u.id},${!u.estado})">${u.estado ? 'Desactivar' : 'Activar'}</button>
          <button class="btn btn-danger btn-sm" onclick="resetPasswordUsuario(${u.id},'${(u.email||'').replace(/'/g,"\\'")}')">🔑 Reset</button>
        </div>
      </td>
    </tr>`).join('');
}

function filtrarUsuarios(q) {
  const ql = q.toLowerCase();
  const f = window._usuarios.filter(u => (u.email||'').toLowerCase().includes(ql) || (u.nombre_perfil||'').toLowerCase().includes(ql));
  document.getElementById('tbody-usuarios').innerHTML = renderFilaUsuarios(f);
}

function filtrarUsuariosPorRol(rol) {
  const f = rol ? window._usuarios.filter(u => u.rol === rol) : window._usuarios;
  document.getElementById('tbody-usuarios').innerHTML = renderFilaUsuarios(f);
}

function toggleGroomerFields() {
  document.getElementById('groomer-extra').style.display =
    document.getElementById('u-rol').value === 'groomer' ? 'block' : 'none';
}

async function toggleUsuario(id, activo) {
  if (!confirm(`¿${activo ? 'Activar' : 'Desactivar'} este usuario?`)) return;
  const res = await Http.put('/admin/usuarios/' + id + '/estado', { activo });
  if (res?.success) { Toast.success('Estado actualizado'); Router.go('usuarios'); }
  else Toast.error(res?.message || 'Error');
}

async function resetPasswordUsuario(id, email) {
  if (!confirm(`¿Resetear la contraseña de ${email}?\n\nSe generará una contraseña temporal.`)) return;
  const res = await Http.post('/admin/usuarios/' + id + '/reset-password', {});
  if (!res?.success) { Toast.error(res?.message || 'Error al resetear'); return; }

  const pwd = res.data?.password_temporal || '—';
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:420px">
      <div class="modal-header">
        <span class="modal-title">✅ Contraseña reseteada</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-16">Contraseña temporal para <strong>${email}</strong>:</p>
        <div style="background:var(--charcoal);color:var(--cream);padding:18px;border-radius:8px;text-align:center;font-family:monospace;font-size:1.4rem;letter-spacing:3px;margin-bottom:16px">
          ${pwd}
        </div>
        ${res.data?.email_enviado
          ? '<div class="alert alert-success">✓ También se envió al correo del usuario.</div>'
          : '<div class="alert alert-warning">⚠ Email no configurado. Entrega esta contraseña manualmente.</div>'}
        <button class="btn btn-secondary w-full mt-8" onclick="navigator.clipboard.writeText('${pwd}').then(()=>Toast.success('Copiada al portapapeles'))">
          📋 Copiar contraseña
        </button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function verDetalleUsuario(id) {
  const res = await Http.get('/admin/usuarios/' + id + '/detalle');
  if (!res?.success) { Toast.error('Error al cargar'); return; }
  const u = res.data;
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:580px">
      <div class="modal-header">
        <span class="modal-title">Detalle — ${u.email}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body" style="max-height:75vh;overflow-y:auto">
        <div class="grid-2 mb-16">
          <div><span class="text-muted text-small">Rol</span><br><span class="badge badge-info">${u.rol}</span></div>
          <div><span class="text-muted text-small">Estado</span><br>${u.estado ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</div>
          <div><span class="text-muted text-small">Nombre</span><br><strong>${u.nombre_cliente||u.nombre_groomer||'—'}</strong></div>
          <div><span class="text-muted text-small">Teléfono</span><br>${u.telefono||'—'}</div>
          <div><span class="text-muted text-small">Autenticación</span><br>${u.oauth_provider ? '🔵 Google OAuth' : '🔑 Email/Contraseña'}</div>
          <div><span class="text-muted text-small">2FA</span><br>${u.two_factor_enabled ? '🔐 Activo' : '⚪ Inactivo'}</div>
          <div><span class="text-muted text-small">Sesiones activas</span><br>${u.sesiones_activas||0}</div>
          <div><span class="text-muted text-small">Intentos fallidos</span><br>${u.intentos_fallidos||0}</div>
          <div><span class="text-muted text-small">Último acceso</span><br>${formatFecha(u.ultimo_acceso)}</div>
          <div><span class="text-muted text-small">Registrado</span><br>${formatFecha(u.created_at)}</div>
        </div>
        ${u.mascotas?.length ? `
          <div class="mb-16">
            <label class="form-label">Mascotas</label>
            ${u.mascotas.map(m => `<span class="badge badge-sage" style="margin-right:6px;margin-bottom:4px">${m.nombre} (${m.raza||m.especie})</span>`).join('')}
          </div>` : ''}
        <div>
          <label class="form-label">Últimas acciones</label>
          ${!u.ultimas_acciones?.length ? '<p class="text-muted text-small">Sin acciones registradas</p>' :
            u.ultimas_acciones.map(a => `
              <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--cream-dark);font-size:.8rem">
                <span>${a.accion}</span>
                <span class="text-muted">${a.ip_address||'—'} · ${formatFecha(a.created_at)}</span>
              </div>`).join('')}
        </div>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function guardarUsuario() {
  const btn = document.getElementById('btn-guardar-usr');
  const msgEl = document.getElementById('msg-usuario');
  btn.disabled = true; btn.innerHTML = '<div class="spinner"></div>';

  const res = await Http.post('/admin/usuarios', {
    email:       document.getElementById('u-email').value.trim(),
    nombre:      document.getElementById('u-nombre').value.trim(),
    rol:         document.getElementById('u-rol').value,
    telefono:    document.getElementById('u-telefono').value.trim(),
    password:    document.getElementById('u-pass').value,
    especialidad: document.getElementById('u-especialidad')?.value?.trim() || '',
    turno:        document.getElementById('u-turno')?.value || 'mañana',
  });

  btn.disabled = false; btn.innerHTML = 'Crear cuenta';

  if (res?.success) { Toast.success('Usuario creado'); closeModal('modal-usuario'); Router.go('usuarios'); }
  else { msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>'; }
}

// ══════════════════════════════════════════════════════════════
// REPORTES
// ══════════════════════════════════════════════════════════════
Router.register('reportes', async () => {
  document.getElementById('page-content').innerHTML = `
    <div class="tabs mb-24" style="flex-wrap:wrap;width:100%">
      <button class="tab-btn active" onclick="cargarReporte('ingresos',this)">💰 Ingresos</button>
      <button class="tab-btn" onclick="cargarReporte('ocupacion',this)">📅 Ocupación</button>
      <button class="tab-btn" onclick="cargarReporte('servicios',this)">✂ Servicios</button>
      <button class="tab-btn" onclick="cargarReporte('productos',this)">📦 Productos</button>
      <button class="tab-btn" onclick="cargarReporte('clientes',this)">👥 Clientes</button>
      <button class="tab-btn" onclick="cargarReporte('cancelaciones',this)">✕ Cancelaciones</button>
      <button class="tab-btn" onclick="cargarReporte('horas',this)">⏰ Horas pico</button>
    </div>
    <div class="flex gap-8 mb-16" style="flex-wrap:wrap">
      <div class="form-group" style="margin:0">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" id="rep-desde" value="${new Date(new Date().getFullYear(),new Date().getMonth(),1).toISOString().split('T')[0]}">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" id="rep-hasta" value="${new Date().toISOString().split('T')[0]}">
      </div>
      <div class="form-group" style="margin:0;align-self:flex-end">
        <button class="btn btn-primary" onclick="recargarReporte()">🔄 Aplicar filtro</button>
      </div>
    </div>
    <div id="reporte-content">
      <div class="flex-center" style="padding:60px"><div class="spinner spinner-dark"></div></div>
    </div>`;
  window._reporteActual = 'ingresos';
  cargarReporte('ingresos');
});

window._reporteActual = 'ingresos';

function recargarReporte() { cargarReporte(window._reporteActual); }

async function cargarReporte(tipo, btn) {
  window._reporteActual = tipo;
  if (btn) { document.querySelectorAll('.tabs .tab-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
  const rc = document.getElementById('reporte-content');
  rc.innerHTML = '<div class="flex-center" style="padding:60px"><div class="spinner spinner-dark"></div></div>';

  const desde = document.getElementById('rep-desde')?.value || '';
  const hasta = document.getElementById('rep-hasta')?.value || '';
  const qs    = `?fecha_inicio=${desde}&fecha_fin=${hasta}`;

  const endpoints = {
    ingresos:      '/reportes/ingresos-diarios' + qs,
    ocupacion:     '/reportes/ocupacion-groomers' + qs,
    servicios:     '/reportes/top-servicios',
    productos:     '/reportes/top-productos',
    clientes:      '/reportes/clientes-frecuentes',
    cancelaciones: '/reportes/cancelaciones' + qs,
    horas:         '/reportes/horas-pico' + qs,
  };

  const configs = {
    ingresos:      { cols:['Fecha','Facturas pagadas','Total del día'],          rows: d => d.map(r=>[r.fecha, r.facturas, formatMoneda(r.total_dia)]) },
    ocupacion:     { cols:['Groomer','Total citas','Min. trabajados','Completadas','Canceladas'], rows: d => d.map(r=>[r.groomer,r.total_citas,r.minutos_trabajados||0,r.completadas,r.canceladas]) },
    servicios:     { cols:['Servicio','Total citas','Ingreso estimado'],          rows: d => d.map(r=>[r.nombre,r.total_citas,formatMoneda(r.ingreso_estimado)]) },
    productos:     { cols:['Producto','SKU','Unidades vendidas','Total ventas'],  rows: d => d.map(r=>[r.nombre,r.sku,r.unidades_vendidas||0,formatMoneda(r.total_ventas)]) },
    clientes:      { cols:['Cliente','Teléfono','Total citas','Gasto total'],     rows: d => d.map(r=>[r.nombre,r.telefono||'—',r.total_citas,formatMoneda(r.gasto_total)]) },
    cancelaciones: { cols:['Groomer','Canceladas','No asistió','Total'],          rows: d => d.map(r=>[r.groomer,r.canceladas,r.no_asistio,r.total]) },
    horas:         { cols:['Hora del día','Total citas'],                         rows: d => d.map(r=>[r.hora+':00',r.total_citas]) },
  };

  const res  = await Http.get(endpoints[tipo]);
  const data = res?.data || [];
  const cfg  = configs[tipo];

  if (!cfg) return;

  const totalRow = tipo === 'ingresos' && data.length
    ? `<tr style="background:var(--cream);font-weight:600"><td colspan="${cfg.cols.length-1}">TOTAL</td><td>${formatMoneda(data.reduce((s,r)=>s+parseFloat(r.total_dia||0),0))}</td></tr>`
    : '';

  rc.innerHTML = `
    <div class="card">
      <div class="card-header">
        <span class="card-title">${cfg.cols[0]} — ${data.length} registros</span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr>${cfg.cols.map(c=>`<th>${c}</th>`).join('')}</tr></thead>
          <tbody>
            ${!data.length
              ? `<tr><td colspan="${cfg.cols.length}" style="text-align:center;color:var(--gray-light);padding:40px">Sin datos en el período seleccionado</td></tr>`
              : cfg.rows(data).map(r=>`<tr>${r.map(v=>`<td>${v}</td>`).join('')}</tr>`).join('')}
            ${totalRow}
          </tbody>
        </table>
      </div>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
// SEGURIDAD
// ══════════════════════════════════════════════════════════════
Router.register('seguridad', async () => {
  document.getElementById('page-content').innerHTML = `
    <div style="max-width:520px;display:flex;flex-direction:column;gap:16px">
      <div class="card">
        <div class="card-body">
          <h3 class="text-serif mb-8">🔐 Autenticación de dos factores (2FA)</h3>
          <p class="text-muted text-small mb-16">Protege tu cuenta con Google Authenticator o Authy.</p>
          <div id="msg-2fa"></div>
          <button class="btn btn-primary" onclick="configurar2FA()">Configurar 2FA</button>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h3 class="text-serif mb-8">🔑 Cambiar contraseña</h3>
          <div id="msg-cambio-pass"></div>
          <div class="form-group">
            <label class="form-label">Contraseña actual</label>
            <input class="form-control" type="password" id="pass-actual" placeholder="••••••••">
          </div>
          <div class="form-group">
            <label class="form-label">Nueva contraseña</label>
            <input class="form-control" type="password" id="pass-nueva" placeholder="Mín. 8 chars, mayús., número, símbolo"
              oninput="mostrarFuerzaPass(this.value,'fuerza-nueva')">
          </div>
          <div id="fuerza-nueva" class="password-strength mb-16"><div class="password-strength-bar"></div></div>
          <div class="form-group">
            <label class="form-label">Confirmar nueva contraseña</label>
            <input class="form-control" type="password" id="pass-confirmar" placeholder="Repite la contraseña">
          </div>
          <button class="btn btn-primary" onclick="cambiarContrasena()">Actualizar contraseña</button>
        </div>
      </div>
    </div>`;
});

function mostrarFuerzaPass(val, elId) {
  let score = 0;
  if (val.length >= 8)           score++;
  if (val.length >= 12)          score++;
  if (/[A-Z]/.test(val))        score++;
  if (/[0-9]/.test(val))        score++;
  if (/[*#!@$%^&]/.test(val))   score++;
  const level = score <= 2 ? 'débil' : score <= 3 ? 'media' : 'fuerte';
  const el = document.getElementById(elId);
  if (el) el.className = 'password-strength strength-' + level;
}

async function configurar2FA() {
  const res = await Http.post('/auth/2fa/setup', {});
  if (!res?.success) { Toast.error('Error al configurar 2FA'); return; }
  document.getElementById('msg-2fa').innerHTML = `
    <div class="alert alert-info mb-16" style="flex-direction:column;align-items:flex-start">
      <strong>URI para tu app de autenticación:</strong>
      <code style="word-break:break-all;font-size:.7rem;margin-top:8px;display:block">${res.data.otpauth_uri}</code>
      <div class="form-group mt-16" style="width:100%">
        <label class="form-label">Código de verificación (6 dígitos)</label>
        <input class="form-control" type="text" id="code-2fa" placeholder="000000" maxlength="6" inputmode="numeric">
      </div>
      <button class="btn btn-primary btn-sm mt-8" onclick="confirmar2FA()">✅ Verificar y activar</button>
    </div>`;
}

async function confirmar2FA() {
  const code = document.getElementById('code-2fa')?.value?.trim();
  if (!code || code.length !== 6) { Toast.error('El código debe ser de 6 dígitos'); return; }
  const res = await Http.post('/auth/2fa/confirm', { code });
  if (res?.success) {
    Toast.success('2FA activado correctamente');
    document.getElementById('msg-2fa').innerHTML = '<div class="alert alert-success">✅ Autenticación de dos factores activa</div>';
  } else {
    Toast.error(res?.message || 'Código inválido');
  }
}

async function cambiarContrasena() {
  const actual     = document.getElementById('pass-actual').value;
  const nueva      = document.getElementById('pass-nueva').value;
  const confirmar  = document.getElementById('pass-confirmar').value;
  const msgEl      = document.getElementById('msg-cambio-pass');

  if (!actual || !nueva) { msgEl.innerHTML = '<div class="alert alert-error">Completa todos los campos</div>'; return; }
  if (nueva !== confirmar) { msgEl.innerHTML = '<div class="alert alert-error">Las contraseñas no coinciden</div>'; return; }

  const res = await Http.put('/auth/change-password', { password_actual: actual, password_nueva: nueva });
  if (res?.success) {
    Toast.success('Contraseña actualizada');
    msgEl.innerHTML = '<div class="alert alert-success">✅ Contraseña actualizada correctamente</div>';
    document.getElementById('pass-actual').value = '';
    document.getElementById('pass-nueva').value = '';
    document.getElementById('pass-confirmar').value = '';
  } else {
    msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// PERFIL CLIENTE
// ══════════════════════════════════════════════════════════════
Router.register('perfil', async () => {
  const user = Auth.user();
  // Obtener datos del cliente
  const res = await Http.get('/clientes/0'); // se obtiene por usuario autenticado
  document.getElementById('page-content').innerHTML = `
    <div style="max-width:480px">
      <div class="card">
        <div class="card-body">
          <h3 class="text-serif mb-24">Mi perfil</h3>
          <div id="msg-perfil"></div>
          <div class="form-group">
            <label class="form-label">Email (no editable)</label>
            <input class="form-control" value="${user?.email||''}" disabled style="background:var(--cream)">
          </div>
          <div class="form-group">
            <label class="form-label">Nombre</label>
            <input class="form-control" id="perf-nombre" placeholder="Tu nombre completo">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input class="form-control" id="perf-tel" placeholder="70000000">
          </div>
          <div class="form-group">
            <label class="form-label">Canal de notificaciones</label>
            <select class="form-control" id="perf-canal">
              <option value="email">📧 Email</option>
              <option value="whatsapp">💬 WhatsApp</option>
            </select>
          </div>
          <button class="btn btn-primary" onclick="Toast.success('Funcionalidad disponible próximamente')">Guardar cambios</button>
        </div>
      </div>
    </div>`;
});

// ══════════════════════════════════════════════════════════════
// TIENDA (cliente)
// ══════════════════════════════════════════════════════════════
Router.register('tienda', async () => {
  const res = await Http.get('/productos');
  window._productostienda = res?.data || [];
  if (!sessionStorage.getItem('cart_token')) sessionStorage.setItem('cart_token', 'cart_' + Date.now());

  const categorias = [...new Set(window._productostienda.map(p => p.categoria).filter(Boolean))];
  const emojiCat   = { shampoos:'🧴', alimentos:'🥩', juguetes:'🎾', accesorios:'🎀' };

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:12px">
      <div class="flex gap-8" style="flex-wrap:wrap">
        <input type="search" class="form-control" placeholder="🔍 Buscar producto..."
          style="min-width:220px" oninput="filtrarTienda(this.value)">
        <select class="form-control" style="width:auto" onchange="filtrarTiendaCat(this.value)">
          <option value="">Todas las categorías</option>
          ${categorias.map(c => `<option value="${c}">${c}</option>`).join('')}
        </select>
      </div>
      <button class="btn btn-secondary" onclick="verCarrito()">
        🛒 Ver carrito <span id="cart-badge" style="background:var(--sage);color:white;border-radius:50%;padding:2px 7px;font-size:.75rem;margin-left:4px">0</span>
      </button>
    </div>
    <div id="tienda-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
      ${renderCardsTienda(window._productostienda, emojiCat)}
    </div>`;

  // Actualizar badge del carrito
  actualizarBadgeCarrito();
});

function renderCardsTienda(productos, emojiCat) {
  emojiCat = emojiCat || {};
  if (!productos.length) return '<div class="text-muted text-small" style="grid-column:1/-1;text-align:center;padding:60px">Sin productos disponibles</div>';
  return productos.map(p => {
    const catKey = (p.categoria||'').toLowerCase();
    const emoji = Object.entries(emojiCat).find(([k]) => catKey.includes(k))?.[1] || '📦';
    return `
    <div class="card" style="transition:transform .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
      <div style="background:var(--cream-dark);height:120px;border-radius:var(--radius-lg) var(--radius-lg) 0 0;display:flex;align-items:center;justify-content:center;font-size:3.5rem">${emoji}</div>
      <div class="card-body">
        <div style="font-weight:600;margin-bottom:4px;font-size:.95rem">${p.nombre}</div>
        <div class="text-muted text-small mb-4">${p.descripcion||''}</div>
        <div class="text-muted text-small mb-12">Stock: ${p.stock} und.</div>
        <div class="flex-between">
          <span style="font-family:var(--font-display);font-size:1.1rem;color:var(--sage-dark)">${formatMoneda(p.precio_base)}</span>
          <button class="btn btn-primary btn-sm" onclick="agregarCarrito(${p.id},'${p.nombre.replace(/'/g,"\\'")}',${p.precio_base})"
            ${p.stock <= 0 ? 'disabled style="opacity:.5"' : ''}>
            ${p.stock <= 0 ? 'Sin stock' : '+ Agregar'}
          </button>
        </div>
      </div>
    </div>`}).join('');
}

function filtrarTienda(q) {
  const ql = q.toLowerCase();
  const f = window._productostienda.filter(p => (p.nombre||'').toLowerCase().includes(ql));
  document.getElementById('tienda-grid').innerHTML = renderCardsTienda(f);
}

function filtrarTiendaCat(cat) {
  const f = cat ? window._productostienda.filter(p => p.categoria === cat) : window._productostienda;
  document.getElementById('tienda-grid').innerHTML = renderCardsTienda(f);
}

async function agregarCarrito(prodId, nombre, precio) {
  const res = await Http.post('/carrito/agregar', {
    producto_id:   prodId, cantidad: 1,
    session_token: sessionStorage.getItem('cart_token'),
  });
  if (res?.success) {
    Toast.success(`"${nombre}" agregado al carrito`);
    actualizarBadgeCarrito();
  } else {
    Toast.error(res?.message || 'Error');
  }
}

async function actualizarBadgeCarrito() {
  const token = sessionStorage.getItem('cart_token');
  const res   = await Http.get('/carrito?session_token=' + token);
  const badge = document.getElementById('cart-badge');
  if (badge && res?.data) badge.textContent = res.data.cantidad_items || 0;
}

async function verCarrito() {
  const token = sessionStorage.getItem('cart_token');
  const res   = await Http.get('/carrito?session_token=' + token);
  const items = res?.data?.items || [];
  const total = res?.data?.total || 0;

  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.id = 'carrito-overlay';
  overlay.innerHTML = `
    <div class="modal" style="max-width:520px">
      <div class="modal-header">
        <span class="modal-title">🛒 Mi carrito (${items.length} items)</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body" style="max-height:60vh;overflow-y:auto">
        ${!items.length ? '<p class="text-muted" style="text-align:center;padding:32px">Tu carrito está vacío.</p>' :
          items.map(i => `
            <div class="flex-between" style="padding:12px 0;border-bottom:1px solid var(--cream-dark)">
              <div>
                <strong>${i.producto}</strong>
                <div class="text-muted text-small">x${i.cantidad} × ${formatMoneda(i.precio_unitario)}</div>
              </div>
              <div class="flex gap-8" style="align-items:center">
                <strong>${formatMoneda(i.subtotal)}</strong>
                <button class="btn btn-danger btn-sm" onclick="quitarItem(${i.id},'carrito-overlay')">✕</button>
              </div>
            </div>`).join('')}
        ${items.length ? `
          <div class="flex-between mt-16">
            <strong style="font-size:1rem">Total:</strong>
            <strong style="font-family:var(--font-display);font-size:1.3rem;color:var(--sage-dark)">${formatMoneda(total)}</strong>
          </div>` : ''}
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Seguir comprando</button>
        ${items.length ? `<button class="btn btn-primary" onclick="pedirPorWhatsApp()">💬 Pedir por WhatsApp</button>` : ''}
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function quitarItem(itemId, overlayId) {
  await Http.delete('/carrito/item/' + itemId);
  const overlay = document.getElementById(overlayId);
  if (overlay) overlay.remove();
  verCarrito();
  actualizarBadgeCarrito();
}

async function pedirPorWhatsApp() {
  const token = sessionStorage.getItem('cart_token');
  const res   = await Http.post('/carrito/pedido', { session_token: token, metodo_contacto: 'whatsapp' });
  if (res?.success) {
    window.open(res.data.whatsapp_link, '_blank');
    Toast.success('¡Pedido #' + res.data.pedido_id + ' creado!');
    document.getElementById('carrito-overlay')?.remove();
  } else {
    Toast.error(res?.message || 'Error al crear pedido');
  }
}

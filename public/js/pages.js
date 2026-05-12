// PET SPA — PÁGINAS SPA

// ══════════════════════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════════════════════
Router.register('dashboard', async () => {
  const res = await Http.get('/reportes/dashboard');
  const c   = document.getElementById('page-content');
  if (!res?.success) {
    c.innerHTML = '<div class="alert alert-error">Error al cargar el dashboard. ' + (res?.message||'Verifica la conexión.') + '</div>';
    return;
  }
  const d   = res.data;
  const rol = d.rol || Auth.rol();

  // Dashboard CLIENTE
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
          <button class="btn btn-primary btn-sm" onclick="window.navigate('citas','Mis Citas')">Agendar cita</button>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Mascota</th><th>Servicio</th><th>Groomer</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
              ${!d.citas_proximas?.length
                ? '<tr><td colspan="5" style="text-align:center;color:var(--gray-light);padding:32px">No tienes citas próximas.<br><a href="#" onclick="window.navigate(\'citas\',\'Mis Citas\')" style="color:var(--sage-dark)">Agendar una →</a></td></tr>'
                : d.citas_proximas.map(ci => `
                  <tr>
                    <td><strong>${ci.mascota}</strong></td>
                    <td>${ci.servicio}</td>
                    <td>${ci.groomer}</td>
                    <td>${formatFecha(ci.fecha_hora_inicio)}</td>
                    <td>${badgeEstadoCita(ci.estado)}</td>
                  </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
    return;
  }

  // Dashboard GROOMER
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
        <div class="card-header">
          <span class="card-title">Mis próximas citas</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Mascota</th><th>Raza</th><th>Servicio</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
              ${!d.proximas_citas?.length
                ? '<tr><td colspan="5" style="text-align:center;color:var(--gray-light);padding:32px">Sin citas próximas</td></tr>'
                : d.proximas_citas.map(ci => `
                  <tr>
                    <td><strong>${ci.mascota}</strong></td>
                    <td class="text-muted text-small">${ci.raza||'—'}</td>
                    <td>${ci.servicio}</td>
                    <td>${formatFecha(ci.fecha_hora_inicio)}</td>
                    <td>${badgeEstadoCita(ci.estado)}</td>
                  </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
    return;
  }

  // Dashboard ADMIN y RECEPCION
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
        <div class="stat-label">Clientes</div>
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
              ? '<tr><td colspan="6" style="text-align:center;color:var(--gray-light);padding:32px">Sin citas próximas</td></tr>'
              : d.proximas_citas.map(ci => `
                <tr>
                  <td class="text-muted text-small">#${ci.id}</td>
                  <td><strong>${ci.mascota}</strong></td>
                  <td>${ci.groomer}</td>
                  <td>${ci.servicio}</td>
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
Router.register('citas', async () => {
  const [citasRes, groomersRes, serviciosRes, clientesRes] = await Promise.all([
    Http.get('/citas'),
    Http.get('/groomers'),
    Http.get('/servicios'),
    Http.get('/clientes'),
  ]);
  const citas     = citasRes?.data    || [];
  const groomers  = groomersRes?.data || [];
  const servicios = serviciosRes?.data || [];
  const clientes  = clientesRes?.data  || [];
  const rol = Auth.user()?.rol;
  window._citas = citas;
  window._slotSeleccionado = null;

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <div class="tabs" id="citas-tabs">
        <button class="tab-btn active" onclick="filtrarCitas('todas',this)">Todas</button>
        <button class="tab-btn" onclick="filtrarCitas('agendada',this)">Agendadas</button>
        <button class="tab-btn" onclick="filtrarCitas('confirmada',this)">Confirmadas</button>
        <button class="tab-btn" onclick="filtrarCitas('en_progreso',this)">En progreso</button>
        <button class="tab-btn" onclick="filtrarCitas('completada',this)">Completadas</button>
      </div>
      ${rol !== 'groomer' ? '<button class="btn btn-primary" onclick="openModal(\'modal-cita\')">+ Nueva cita</button>' : ''}
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Cliente</th><th>Mascota</th><th>Groomer</th><th>Servicio</th><th>Fecha / Hora</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody id="tbody-citas">${renderFilaCitas(citas)}</tbody>
        </table>
      </div>
    </div>

    <div class="modal-overlay" id="modal-cita">
      <div class="modal" style="max-width:600px">
        <div class="modal-header">
          <span class="modal-title">Nueva cita</span>
          <button class="modal-close" onclick="closeModal('modal-cita')">×</button>
        </div>
        <div class="modal-body">
          <div id="msg-cita"></div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Cliente *</label>
              <select class="form-control" id="cita-cliente" onchange="cargarMascotasCliente()">
                <option value="">Seleccionar cliente...</option>
                ${clientes.map(c => `<option value="${c.id}">${c.nombre} — ${c.telefono||c.email||''}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Mascota *</label>
              <select class="form-control" id="cita-mascota">
                <option value="">Selecciona un cliente primero</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Groomer *</label>
              <select class="form-control" id="cita-groomer" onchange="cargarSlots()">
                <option value="">Seleccionar groomer...</option>
                ${groomers.map(g => `<option value="${g.id}">${g.nombre}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Servicio *</label>
              <select class="form-control" id="cita-servicio" onchange="cargarSlots()">
                <option value="">Seleccionar servicio...</option>
                ${servicios.map(s => `<option value="${s.id}">${s.nombre} — ${formatMoneda(s.precio_base)}</option>`).join('')}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha *</label>
            <input type="date" class="form-control" id="cita-fecha"
              min="${new Date().toISOString().split('T')[0]}"
              onchange="cargarSlots()">
          </div>
          <div class="form-group" id="slots-wrap" style="display:none">
            <label class="form-label">Horarios disponibles</label>
            <div class="slots-grid" id="slots-grid"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea class="form-control" id="cita-notas" rows="2" placeholder="Indicaciones especiales..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-cita')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarCita()">Agendar cita</button>
        </div>
      </div>
    </div>`;
});

function renderFilaCitas(citas) {
  if (!citas.length) return '<tr><td colspan="8" style="text-align:center;color:var(--gray-light);padding:40px">Sin citas registradas</td></tr>';
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
        <div class="flex gap-8">
          ${c.estado==='agendada'   ? `<button class="btn btn-secondary btn-sm" onclick="cambiarEstadoCita(${c.id},'confirmada')">✓ Confirmar</button>` : ''}
          ${c.estado==='confirmada' ? `<button class="btn btn-secondary btn-sm" onclick="cambiarEstadoCita(${c.id},'en_progreso')">▶ Iniciar</button>` : ''}
          ${c.estado==='en_progreso'? `<button class="btn btn-primary btn-sm" onclick="window.navigate('fichas','Fichas')">📋 Ficha</button>` : ''}
          ${['agendada','confirmada'].includes(c.estado) ? `<button class="btn btn-danger btn-sm" onclick="cambiarEstadoCita(${c.id},'cancelada')">✕</button>` : ''}
        </div>
      </td>
    </tr>`).join('');
}

function filtrarCitas(estado, btn) {
  document.querySelectorAll('#citas-tabs .tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const f = estado === 'todas' ? window._citas : window._citas.filter(c => c.estado === estado);
  document.getElementById('tbody-citas').innerHTML = renderFilaCitas(f);
}

async function cargarMascotasCliente() {
  const clienteId = document.getElementById('cita-cliente').value;
  const sel = document.getElementById('cita-mascota');
  if (!clienteId) { sel.innerHTML = '<option value="">Selecciona un cliente primero</option>'; return; }
  const res = await Http.get('/mascotas');
  const todas = res?.data || [];
  // Filtrar mascotas del cliente seleccionado
  const res2 = await Http.get('/clientes/' + clienteId);
  const mascotas = res2?.data?.mascotas || [];
  if (!mascotas.length) {
    sel.innerHTML = '<option value="">Este cliente no tiene mascotas</option>';
    return;
  }
  sel.innerHTML = '<option value="">Seleccionar mascota...</option>' +
    mascotas.map(m => `<option value="${m.id}">${m.nombre} (${m.raza||m.especie||'—'})</option>`).join('');
}

async function cargarSlots() {
  const gid   = document.getElementById('cita-groomer').value;
  const fecha = document.getElementById('cita-fecha').value;
  const srvId = document.getElementById('cita-servicio').value;
  if (!gid || !fecha) return;
  const wrap = document.getElementById('slots-wrap');
  const grid = document.getElementById('slots-grid');
  wrap.style.display = 'block';
  grid.innerHTML = '<span class="text-muted text-small">Cargando horarios...</span>';
  const res = await Http.get('/disponibilidad?groomer_id=' + gid + '&fecha=' + fecha + '&servicio_id=' + (srvId||''));
  if (!res?.data?.slots?.length) {
    grid.innerHTML = '<p class="text-muted text-small">Sin slots disponibles para esa fecha.</p>';
    return;
  }
  window._slotSeleccionado = null;
  grid.innerHTML = res.data.slots.map(slot =>
    `<button class="slot-btn" onclick="seleccionarSlot('${fecha} ${slot}:00', this)">${slot}</button>`
  ).join('');
}

function seleccionarSlot(datetime, btn) {
  document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  window._slotSeleccionado = datetime;
}

async function guardarCita() {
  const clienteId = parseInt(document.getElementById('cita-cliente').value);
  const mascotaId = parseInt(document.getElementById('cita-mascota').value);
  const groId     = parseInt(document.getElementById('cita-groomer').value);
  const srvId     = parseInt(document.getElementById('cita-servicio').value);
  const msgEl     = document.getElementById('msg-cita');

  if (!clienteId || !mascotaId || !groId || !srvId || !window._slotSeleccionado) {
    msgEl.innerHTML = '<div class="alert alert-error">Completa todos los campos y selecciona un horario.</div>';
    return;
  }

  const body = {
    cliente_id:        clienteId,
    mascota_id:        mascotaId,
    groomer_id:        groId,
    servicio_id:       srvId,
    fecha_hora_inicio: window._slotSeleccionado,
    notas:             document.getElementById('cita-notas').value,
  };

  const res = await Http.post('/citas', body);
  if (res?.success) {
    Toast.success('Cita agendada correctamente');
    closeModal('modal-cita');
    Router.go('citas');
  } else {
    msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message || 'Error al agendar') + '</div>';
  }
}

async function cambiarEstadoCita(id, estado) {
  if (!confirm('¿Cambiar cita #' + id + ' a "' + estado + '"?')) return;
  const res = await Http.put('/citas/' + id + '/estado', { estado });
  if (res?.success) { Toast.success('Estado actualizado'); Router.go('citas'); }
  else Toast.error(res?.message || 'Error');
}

// ══════════════════════════════════════════════════════════════
// FICHAS DE GROOMING
// ══════════════════════════════════════════════════════════════
Router.register('fichas', async () => {
  const c = document.getElementById('page-content');
  const res = await Http.get('/citas');
  const enProgreso = (res?.data || []).filter(c => c.estado === 'en_progreso');

  c.innerHTML = `
    <div class="card">
      <div class="card-header">
        <span class="card-title">Fichas de Grooming activas</span>
        <span class="text-muted text-small">Se crean al iniciar una cita</span>
      </div>
      <div class="card-body" id="fichas-list">
        ${!enProgreso.length
          ? '<p class="text-muted" style="text-align:center;padding:32px">No hay fichas activas en este momento.</p>'
          : enProgreso.map(ci => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--cream-dark)">
              <div>
                <strong>${ci.mascota_nombre}</strong> — ${ci.servicio_nombre}<br>
                <span class="text-muted text-small">Groomer: ${ci.groomer_nombre} · ${formatFecha(ci.fecha_hora_inicio)}</span>
              </div>
              <button class="btn btn-primary btn-sm" onclick="abrirFicha(${ci.id},'${ci.mascota_nombre}')">Abrir ficha →</button>
            </div>`).join('')}
      </div>
    </div>

    <div class="modal-overlay" id="modal-ficha">
      <div class="modal" style="max-width:640px">
        <div class="modal-header">
          <span class="modal-title" id="ficha-modal-title">Ficha de Grooming</span>
          <button class="modal-close" onclick="closeModal('modal-ficha')">×</button>
        </div>
        <div class="modal-body" id="ficha-modal-body"></div>
        <div class="modal-footer" id="ficha-modal-footer"></div>
      </div>
    </div>`;
});

function abrirFicha(citaId, mascotaNombre) {
  document.getElementById('ficha-modal-title').textContent = 'Ficha: ' + mascotaNombre;
  document.getElementById('ficha-modal-body').innerHTML = `
    <div id="msg-ficha"></div>
    <div class="grid-2 mb-16">
      <div class="form-group">
        <label class="form-label">Estado inicial del animal</label>
        <textarea class="form-control" id="ficha-estado-ini" rows="2" placeholder="Condición al ingreso..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Temperatura (°C)</label>
        <input type="number" class="form-control" id="ficha-temp" placeholder="38.5" step="0.1">
      </div>
    </div>
    <div class="form-group mb-16">
      <label class="form-label">Notas internas del equipo</label>
      <textarea class="form-control" id="ficha-notas" rows="2" placeholder="Solo visible para el personal..."></textarea>
    </div>
    <div style="margin-bottom:20px">
      <label class="form-label">Checklist de servicios</label>
      ${['Baño','Corte de pelo','Corte de uñas','Limpieza de oídos','Glándulas anales','Perfume'].map((item, i) => `
        <div class="checklist-item" id="cli-${i}">
          <div class="check-box" id="cb-${i}" onclick="toggleCheck(${i})"></div>
          <span class="checklist-label">${item}</span>
        </div>`).join('')}
    </div>
    <div class="grid-2">
      <div>
        <label class="form-label">📷 Fotos ANTES</label>
        <div class="foto-drop" onclick="document.getElementById('foto-antes').click()">
          <div class="foto-drop-icon">🐶</div>
          <div class="foto-drop-label">Clic para subir foto antes</div>
          <input type="file" id="foto-antes" accept="image/*" style="display:none">
        </div>
        <div id="preview-antes" class="mt-8"></div>
      </div>
      <div>
        <label class="form-label">📷 Fotos DESPUÉS</label>
        <div class="foto-drop" onclick="document.getElementById('foto-despues').click()">
          <div class="foto-drop-icon">✨</div>
          <div class="foto-drop-label">Clic para subir foto después</div>
          <input type="file" id="foto-despues" accept="image/*" style="display:none">
        </div>
        <div id="preview-despues" class="mt-8"></div>
      </div>
    </div>`;

  ['antes','despues'].forEach(tipo => {
    document.getElementById('foto-' + tipo).addEventListener('change', function() {
      const file = this.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        document.getElementById('preview-' + tipo).innerHTML =
          '<img src="' + e.target.result + '" style="width:100%;border-radius:8px;max-height:120px;object-fit:cover">';
      };
      reader.readAsDataURL(file);
    });
  });

  document.getElementById('ficha-modal-footer').innerHTML = `
    <button class="btn btn-secondary" onclick="closeModal('modal-ficha')">Cancelar</button>
    <button class="btn btn-primary" onclick="Toast.success('Ficha guardada');closeModal('modal-ficha')">Guardar ficha</button>`;

  openModal('modal-ficha');
}

const _checks = {};
function toggleCheck(i) {
  _checks[i] = !_checks[i];
  const cb  = document.getElementById('cb-' + i);
  const row = document.getElementById('cli-' + i);
  cb.classList.toggle('checked', _checks[i]);
  cb.textContent = _checks[i] ? '✓' : '';
  row.classList.toggle('done', _checks[i]);
}

// ══════════════════════════════════════════════════════════════
// CLIENTES
// ══════════════════════════════════════════════════════════════
Router.register('clientes', async () => {
  const res = await Http.get('/clientes');
  const clientes = res?.data || [];
  window._clientes = clientes;

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <input type="search" class="form-control" placeholder="🔍 Buscar por nombre, teléfono o CI..."
        style="max-width:320px" oninput="filtrarClientes(this.value)">
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Cliente</th><th>Email</th><th>Teléfono</th><th>CI</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody id="tbody-clientes">${renderFilaClientes(clientes)}</tbody>
        </table>
      </div>
    </div>`;
});

function renderFilaClientes(clientes) {
  if (!clientes.length) return '<tr><td colspan="6" style="text-align:center;color:var(--gray-light);padding:40px">Sin clientes</td></tr>';
  return clientes.map(c => `
    <tr>
      <td>
        <div class="flex gap-12">
          <div class="user-avatar" style="background:var(--sage)">${iniciales(c.nombre)}</div>
          <div><strong>${c.nombre}</strong></div>
        </div>
      </td>
      <td class="text-muted text-small">${c.email||'—'}</td>
      <td>${c.telefono||'—'}</td>
      <td>${c.ci||'—'}</td>
      <td>${c.cuenta_activa ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</td>
      <td><button class="btn btn-secondary btn-sm" onclick="verCliente(${c.id})">Ver historial</button></td>
    </tr>`).join('');
}

function filtrarClientes(q) {
  const f = window._clientes.filter(c =>
    (c.nombre||'').toLowerCase().includes(q.toLowerCase()) ||
    (c.telefono||'').includes(q) || (c.ci||'').includes(q));
  document.getElementById('tbody-clientes').innerHTML = renderFilaClientes(f);
}

async function verCliente(id) {
  const res = await Http.get('/clientes/' + id + '/historial');
  const hist = res?.data || [];
  const c = window._clientes.find(x => x.id == id);
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:600px">
      <div class="modal-header">
        <span class="modal-title">Historial — ${c?.nombre||'cliente'}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        ${!hist.length ? '<p class="text-muted">Sin historial de citas.</p>' :
          hist.map(h => `
            <div style="padding:12px 0;border-bottom:1px solid var(--cream-dark)">
              <div class="flex-between">
                <strong>${h.mascota||'—'}</strong>
                ${badgeEstadoCita(h.estado)}
              </div>
              <div class="text-muted text-small mt-8">${h.servicio||'—'} · ${formatFecha(h.fecha_hora_inicio)} · ${formatMoneda(h.precio_base)}</div>
            </div>`).join('')}
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

// ══════════════════════════════════════════════════════════════
// MASCOTAS
// ══════════════════════════════════════════════════════════════
Router.register('mascotas', async () => {
  const [mascRes, clientesRes] = await Promise.all([
    Http.get('/mascotas'),
    Http.get('/clientes'),
  ]);
  const mascotas = mascRes?.data || [];
  const clientes = clientesRes?.data || [];
  window._mascotas = mascotas;

  const tempIcons = { tranquilo:'😊', jugueton:'🎾', agresivo:'⚠️' };

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <input type="search" class="form-control" placeholder="🔍 Buscar mascota..."
        style="max-width:300px" oninput="filtrarMascotas(this.value)">
      <button class="btn btn-primary" onclick="openModal('modal-mascota')">+ Agregar mascota</button>
    </div>
    <div id="mascotas-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
      ${renderCardsMascotas(mascotas, tempIcons)}
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
              <input class="form-control" type="number" id="m-peso" placeholder="15.5" step="0.1">
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
                <option value="tranquilo">Tranquilo</option>
                <option value="jugueton">Juguetón</option>
                <option value="agresivo">Agresivo</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Alergias conocidas</label>
            <input class="form-control" id="m-alergias" placeholder="Ninguna / pollo / ...">
          </div>
          <div class="form-group">
            <label class="form-label">Restricciones médicas</label>
            <textarea class="form-control" id="m-restricciones" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Dueño (cliente) *</label>
            <select class="form-control" id="m-cliente-id">
              <option value="">Seleccionar cliente...</option>
              ${clientes.map(c => `<option value="${c.id}">${c.nombre} — ${c.telefono||c.email||''}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-mascota')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarMascota()">Registrar</button>
        </div>
      </div>
    </div>`;
});

function renderCardsMascotas(mascotas, tempIcons) {
  tempIcons = tempIcons || { tranquilo:'😊', jugueton:'🎾', agresivo:'⚠️' };
  if (!mascotas.length) return '<div class="text-muted text-small" style="grid-column:1/-1;text-align:center;padding:60px">Sin mascotas registradas</div>';
  return mascotas.map(m => `
    <div class="card">
      <div style="background:var(--cream-dark);padding:24px;text-align:center;border-radius:var(--radius-lg) var(--radius-lg) 0 0">
        <div style="font-size:3rem">${m.especie==='Gato'?'🐱':'🐶'}</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;margin-top:8px">${m.nombre}</div>
        <div class="text-muted text-small">${m.raza||'—'} ${m.peso_kg ? '· ' + m.peso_kg + ' kg' : ''}</div>
      </div>
      <div style="padding:16px">
        <div class="flex gap-8 flex-wrap">
          ${m.temperamento ? `<span class="badge badge-gray">${tempIcons[m.temperamento]||''} ${m.temperamento}</span>` : ''}
          ${m.alergias && m.alergias !== 'Ninguna' ? `<span class="badge badge-terra">⚠ Alergias</span>` : ''}
          ${m.dueno_nombre ? `<span class="badge badge-sage">👤 ${m.dueno_nombre}</span>` : ''}
        </div>
      </div>
    </div>`).join('');
}

function filtrarMascotas(q) {
  const f = window._mascotas.filter(m =>
    (m.nombre||'').toLowerCase().includes(q.toLowerCase()) ||
    (m.raza||'').toLowerCase().includes(q.toLowerCase()));
  const tempIcons = { tranquilo:'😊', jugueton:'🎾', agresivo:'⚠️' };
  document.getElementById('mascotas-grid').innerHTML = renderCardsMascotas(f, tempIcons);
}

async function guardarMascota() {
  const clienteId = parseInt(document.getElementById('m-cliente-id').value);
  if (!clienteId) {
    document.getElementById('msg-mascota').innerHTML = '<div class="alert alert-error">Selecciona el dueño (cliente)</div>';
    return;
  }
  const nombre = document.getElementById('m-nombre').value.trim();
  if (!nombre) {
    document.getElementById('msg-mascota').innerHTML = '<div class="alert alert-error">El nombre es requerido</div>';
    return;
  }
  const body = {
    nombre,
    especie:    document.getElementById('m-especie').value,
    raza:       document.getElementById('m-raza').value.trim(),
    peso_kg:    parseFloat(document.getElementById('m-peso').value) || null,
    fecha_nacimiento: document.getElementById('m-nacimiento').value || null,
    temperamento: document.getElementById('m-temperamento').value,
    alergias:   document.getElementById('m-alergias').value.trim(),
    restricciones_medicas: document.getElementById('m-restricciones').value.trim(),
    cliente_id: clienteId,
  };
  const res = await Http.post('/mascotas', body);
  if (res?.success) {
    Toast.success('Mascota registrada');
    closeModal('modal-mascota');
    Router.go('mascotas');
  } else {
    document.getElementById('msg-mascota').innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// SERVICIOS
// ══════════════════════════════════════════════════════════════
Router.register('servicios', async () => {
  const res = await Http.get('/servicios');
  const servicios = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <span class="text-muted text-small">${servicios.length} servicios activos</span>
      <button class="btn btn-primary" onclick="openModal('modal-servicio')">+ Nuevo servicio</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      ${servicios.map(s => `
        <div class="card" style="border-top:3px solid var(--sage)">
          <div class="card-body">
            <div class="flex-between mb-16">
              <h3 class="text-serif">${s.nombre}</h3>
              <span class="badge badge-sage">${s.duracion_base_minutos} min</span>
            </div>
            <p class="text-muted text-small mb-16">${s.descripcion||'—'}</p>
            <div class="flex-between">
              <span style="font-size:1.3rem;font-family:var(--font-display);color:var(--sage-dark)">${formatMoneda(s.precio_base)}</span>
              ${s.permite_doble_booking ? '<span class="badge badge-info">Doble booking</span>' : ''}
            </div>
          </div>
        </div>`).join('')}
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
            <textarea class="form-control" id="s-desc" rows="2"></textarea>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Duración base (min) — múltiplo de 15</label>
              <input class="form-control" type="number" id="s-duracion" placeholder="60" step="15" min="15">
            </div>
            <div class="form-group">
              <label class="form-label">Precio base (Bs)</label>
              <input class="form-control" type="number" id="s-precio" placeholder="80.00" step="0.50" min="0">
            </div>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" id="s-doble"> Permite doble booking simultáneo
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-servicio')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarServicio()">Crear servicio</button>
        </div>
      </div>
    </div>`;
});

async function guardarServicio() {
  const nombre  = document.getElementById('s-nombre').value.trim();
  const dur     = parseInt(document.getElementById('s-duracion').value);
  const precio  = parseFloat(document.getElementById('s-precio').value);
  const msgEl   = document.getElementById('msg-servicio');

  if (!nombre) { msgEl.innerHTML = '<div class="alert alert-error">El nombre es requerido</div>'; return; }
  if (!dur || dur % 15 !== 0) { msgEl.innerHTML = '<div class="alert alert-error">La duración debe ser múltiplo de 15 minutos</div>'; return; }
  if (isNaN(precio) || precio < 0) { msgEl.innerHTML = '<div class="alert alert-error">Precio inválido</div>'; return; }

  const body = {
    nombre,
    descripcion: document.getElementById('s-desc').value.trim(),
    duracion_base_minutos: dur,
    precio_base: precio,
    permite_doble_booking: document.getElementById('s-doble').checked,
  };
  const res = await Http.post('/servicios', body);
  if (res?.success) {
    Toast.success('Servicio creado');
    closeModal('modal-servicio');
    Router.go('servicios');
  } else {
    msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// PRODUCTOS
// ══════════════════════════════════════════════════════════════
Router.register('productos', async () => {
  const [prodRes, bajosRes] = await Promise.all([Http.get('/productos'), Http.get('/productos/bajo-stock')]);
  const productos = prodRes?.data || [];
  const bajos     = bajosRes?.data || [];
  window._productos = productos;

  document.getElementById('page-content').innerHTML = `
    ${bajos.length ? `<div class="alert alert-warning mb-16">⚠️ <strong>Bajo stock:</strong> ${bajos.map(p => p.nombre + ' (' + p.stock + '/' + p.stock_minimo + ')').join(' · ')}</div>` : ''}
    <div class="flex-between mb-24">
      <input type="search" class="form-control" placeholder="🔍 Buscar producto o SKU..."
        style="max-width:300px" oninput="filtrarProductos(this.value)">
      <button class="btn btn-primary" onclick="openModal('modal-producto')">+ Nuevo producto</button>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Producto</th><th>SKU</th><th>Precio</th><th>Stock</th><th>Categoría</th></tr></thead>
          <tbody id="tbody-productos">${renderFilaProductos(productos)}</tbody>
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
              <input class="form-control" type="number" id="p-precio" step="0.50" min="0">
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
              <input class="form-control" id="p-desc" placeholder="...">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-producto')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarProducto()">Crear producto</button>
        </div>
      </div>
    </div>`;
});

function renderFilaProductos(prods) {
  if (!prods.length) return '<tr><td colspan="5" style="text-align:center;color:var(--gray-light);padding:40px">Sin productos</td></tr>';
  return prods.map(p => `
    <tr>
      <td><strong>${p.nombre}</strong><br><span class="text-muted text-small">${p.descripcion||''}</span></td>
      <td><code style="font-size:.8rem;background:var(--cream);padding:2px 6px;border-radius:4px">${p.sku}</code></td>
      <td>${formatMoneda(p.precio_base)}</td>
      <td><span class="${p.stock <= p.stock_minimo ? 'badge badge-terra' : 'badge badge-sage'}">${p.stock}</span></td>
      <td class="text-muted text-small">${p.categoria||'—'}</td>
    </tr>`).join('');
}

function filtrarProductos(q) {
  const f = window._productos.filter(p =>
    (p.nombre||'').toLowerCase().includes(q.toLowerCase()) || (p.sku||'').includes(q));
  document.getElementById('tbody-productos').innerHTML = renderFilaProductos(f);
}

async function guardarProducto() {
  const nombre = document.getElementById('p-nombre').value.trim();
  const sku    = document.getElementById('p-sku').value.trim();
  const precio = parseFloat(document.getElementById('p-precio').value);
  const msgEl  = document.getElementById('msg-producto');

  if (!nombre || !sku) { msgEl.innerHTML = '<div class="alert alert-error">Nombre y SKU son requeridos</div>'; return; }
  if (isNaN(precio) || precio < 0) { msgEl.innerHTML = '<div class="alert alert-error">Precio inválido</div>'; return; }

  const body = {
    nombre, sku,
    precio_base:  precio,
    stock:        parseInt(document.getElementById('p-stock').value) || 0,
    stock_minimo: parseInt(document.getElementById('p-stock-min').value) || 5,
    descripcion:  document.getElementById('p-desc').value.trim(),
  };
  const res = await Http.post('/productos', body);
  if (res?.success) {
    Toast.success('Producto creado');
    closeModal('modal-producto');
    Router.go('productos');
  } else {
    msgEl.innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// FACTURAS
// ══════════════════════════════════════════════════════════════
Router.register('facturas', async () => {
  const res = await Http.get('/facturas');
  const facturas = res?.data || [];
  window._facturas = facturas;

  document.getElementById('page-content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <span class="card-title">Facturas</span>
        <select class="form-control" style="width:auto" onchange="filtrarFacturas(this.value)">
          <option value="">Todos los estados</option>
          <option value="pendiente">Pendiente</option>
          <option value="pagada">Pagada</option>
          <option value="cancelada">Cancelada</option>
        </select>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Método pago</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
          <tbody id="tbody-facturas">${renderFilaFacturas(facturas)}</tbody>
        </table>
      </div>
    </div>`;
});

function renderFilaFacturas(facturas) {
  if (!facturas.length) return '<tr><td colspan="7" style="text-align:center;color:var(--gray-light);padding:40px">Sin facturas</td></tr>';
  const map = { pendiente:'badge-warning', pagada:'badge-sage', cancelada:'badge-terra' };
  return facturas.map(f => `
    <tr>
      <td class="text-muted text-small">#${f.numero||f.id}</td>
      <td>${f.cliente_nombre||'—'}</td>
      <td><strong>${formatMoneda(f.total)}</strong></td>
      <td>${f.metodo_pago||'—'}</td>
      <td><span class="badge ${map[f.estado]||'badge-gray'}">${f.estado}</span></td>
      <td class="text-small">${formatFecha(f.created_at)}</td>
      <td>${f.estado==='pendiente' ? `<button class="btn btn-primary btn-sm" onclick="registrarPago(${f.id},${f.total})">Registrar pago</button>` : ''}</td>
    </tr>`).join('');
}

function filtrarFacturas(estado) {
  const f = estado ? window._facturas.filter(f => f.estado === estado) : window._facturas;
  document.getElementById('tbody-facturas').innerHTML = renderFilaFacturas(f);
}

async function registrarPago(id, total) {
  const monto = prompt('Monto a pagar (Total: Bs ' + total + '):', total);
  if (!monto) return;
  const res = await Http.post('/facturas/' + id + '/pago', { monto: parseFloat(monto), metodo: 'efectivo' });
  if (res?.success) { Toast.success('Pago registrado'); Router.go('facturas'); }
  else Toast.error(res?.message || 'Error');
}

// ══════════════════════════════════════════════════════════════
// GROOMERS
// ══════════════════════════════════════════════════════════════
Router.register('groomers', async () => {
  const res = await Http.get('/groomers');
  const groomers = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      ${groomers.map(g => `
        <div class="card">
          <div style="background:linear-gradient(135deg,var(--charcoal),#3d3d3d);padding:24px;border-radius:var(--radius-lg) var(--radius-lg) 0 0">
            <div class="flex gap-12">
              <div class="user-avatar" style="width:48px;height:48px;font-size:1rem;background:var(--sage)">${iniciales(g.nombre)}</div>
              <div>
                <div style="color:var(--cream);font-weight:600">${g.nombre}</div>
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
            <div class="text-muted text-small">${g.telefono||'—'}</div>
            <button class="btn btn-secondary btn-sm mt-16" onclick="verDisponibilidad(${g.id},'${g.nombre}')">📅 Ver disponibilidad</button>
          </div>
        </div>`).join('')}
    </div>`;
});

async function verDisponibilidad(id, nombre) {
  const res  = await Http.get('/groomers/' + id + '/disponibilidad');
  const dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
  const dispo = res?.data || [];
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">Disponibilidad — ${nombre}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        ${!dispo.length ? '<p class="text-muted">Sin horarios configurados.</p>' :
          dispo.map(d => `
            <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--cream-dark)">
              <strong>${dias[d.dia_semana]}</strong>
              <span>${d.hora_inicio} — ${d.hora_fin}</span>
              <span class="text-muted text-small">Buffer: ${d.buffer_minutos}min</span>
            </div>`).join('')}
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

// ══════════════════════════════════════════════════════════════
// USUARIOS
// ══════════════════════════════════════════════════════════════
Router.register('usuarios', async () => {
  const res = await Http.get('/admin/usuarios');
  const usuarios = res?.data || [];

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <span class="text-muted text-small">${usuarios.length} usuarios registrados</span>
      <button class="btn btn-primary" onclick="openModal('modal-usuario')">+ Crear personal</button>
    </div>
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Email</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
          <tbody>
            ${usuarios.map(u => `
              <tr>
                <td>${u.email}</td>
                <td><span class="badge badge-info">${u.rol}</span></td>
                <td>${u.estado ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</td>
                <td class="text-muted text-small">${u.ultimo_acceso ? formatFecha(u.ultimo_acceso) : 'Nunca'}</td>
                <td>
                  <div class="flex gap-8">
                    <button class="btn btn-secondary btn-sm" onclick="toggleUsuario(${u.id},${!u.estado})">${u.estado ? 'Desactivar' : 'Activar'}</button>
                    <button class="btn btn-danger btn-sm" onclick="resetPasswordUsuario(${u.id},'${u.email}')">🔑 Resetear clave</button>
                    <button class="btn btn-secondary btn-sm" onclick="verDetalleUsuario(${u.id})">👁 Ver</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
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
          <p class="text-muted text-small mb-16">Solo admin puede crear cuentas de recepción y groomers.</p>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input class="form-control" type="email" id="u-email" placeholder="groomer@petspa.bo">
          </div>
          <div class="form-group">
            <label class="form-label">Nombre completo</label>
            <input class="form-control" id="u-nombre" placeholder="María López">
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
              <label class="form-label">Teléfono</label>
              <input class="form-control" id="u-telefono" placeholder="70000000">
            </div>
          </div>
          <div id="groomer-extra">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Especialidad</label>
                <input class="form-control" id="u-especialidad" placeholder="Corte fino">
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
          <div class="form-group">
            <label class="form-label">Contraseña temporal *</label>
            <input class="form-control" type="password" id="u-pass" placeholder="Mín. 8 chars, mayús., número, símbolo">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('modal-usuario')">Cancelar</button>
          <button class="btn btn-primary" onclick="guardarUsuario()">Crear cuenta</button>
        </div>
      </div>
    </div>`;
});

function toggleGroomerFields() {
  const es = document.getElementById('u-rol').value === 'groomer';
  document.getElementById('groomer-extra').style.display = es ? 'block' : 'none';
}

async function resetPasswordUsuario(id, email) {
  if (!confirm('¿Resetear la contraseña de ' + email + '?\n\nSe generará una contraseña temporal y se mostrará en pantalla.')) return;
  const res = await Http.post('/admin/usuarios/' + id + '/reset-password', {});
  if (res?.success) {
    const pwd = res.data?.password_temporal || '—';
    const enviado = res.data?.email_enviado;
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
          <div style="background:var(--charcoal);color:var(--cream);padding:16px;border-radius:8px;text-align:center;font-family:monospace;font-size:1.3rem;letter-spacing:2px;margin-bottom:16px">
            ${pwd}
          </div>
          ${enviado
            ? '<div class="alert alert-success">✓ También se envió al correo del usuario.</div>'
            : '<div class="alert alert-warning">⚠ Email no configurado. Entrega esta contraseña manualmente.</div>'}
          <button class="btn btn-secondary w-full mt-8" onclick="navigator.clipboard.writeText(\'${pwd}\');Toast.success(\'Copiada al portapapeles\')">
            📋 Copiar contraseña
          </button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
  } else {
    Toast.error(res?.message || 'Error al resetear');
  }
}

async function verDetalleUsuario(id) {
  const res = await Http.get('/admin/usuarios/' + id + '/detalle');
  if (!res?.success) { Toast.error('Error al cargar'); return; }
  const u = res.data;
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal" style="max-width:560px">
      <div class="modal-header">
        <span class="modal-title">Detalle — ${u.email}</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        <div class="grid-2 mb-16">
          <div><span class="text-muted text-small">Rol</span><br><span class="badge badge-info">${u.rol}</span></div>
          <div><span class="text-muted text-small">Estado</span><br>${u.estado ? '<span class="badge badge-sage">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</div>
          <div><span class="text-muted text-small">Nombre</span><br><strong>${u.nombre_cliente || u.nombre_groomer || '—'}</strong></div>
          <div><span class="text-muted text-small">Teléfono</span><br>${u.telefono || '—'}</div>
          <div><span class="text-muted text-small">Login provider</span><br>${u.oauth_provider ? '🔵 Google OAuth' : '🔑 Email/Password'}</div>
          <div><span class="text-muted text-small">Último acceso</span><br>${formatFecha(u.ultimo_acceso)}</div>
          <div><span class="text-muted text-small">Intentos fallidos</span><br>${u.intentos_fallidos}</div>
          <div><span class="text-muted text-small">Sesiones activas</span><br>${u.sesiones_activas}</div>
        </div>
        <div style="margin-bottom:12px">
          <label class="form-label">Últimas acciones (audit log)</label>
          ${!u.ultimas_acciones?.length ? '<p class="text-muted text-small">Sin acciones registradas</p>' :
            u.ultimas_acciones.map(a => `
              <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--cream-dark);font-size:.8rem">
                <span>${a.accion}</span>
                <span class="text-muted">${a.ip_address} · ${formatFecha(a.created_at)}</span>
              </div>`).join('')}
        </div>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function toggleUsuario(id, activo) {
  const res = await Http.put('/admin/usuarios/' + id + '/estado', { activo });
  if (res?.success) { Toast.success('Estado actualizado'); Router.go('usuarios'); }
  else Toast.error(res?.message || 'Error');
}

async function guardarUsuario() {
  const body = {
    email:       document.getElementById('u-email').value.trim(),
    nombre:      document.getElementById('u-nombre').value.trim(),
    rol:         document.getElementById('u-rol').value,
    telefono:    document.getElementById('u-telefono').value.trim(),
    password:    document.getElementById('u-pass').value,
    especialidad: document.getElementById('u-especialidad')?.value?.trim() || '',
    turno:        document.getElementById('u-turno')?.value || 'mañana',
  };
  const res = await Http.post('/admin/usuarios', body);
  if (res?.success) { Toast.success('Usuario creado'); closeModal('modal-usuario'); Router.go('usuarios'); }
  else document.getElementById('msg-usuario').innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>';
}

// ══════════════════════════════════════════════════════════════
// REPORTES
// ══════════════════════════════════════════════════════════════
Router.register('reportes', async () => {
  document.getElementById('page-content').innerHTML = `
    <div class="tabs mb-24">
      <button class="tab-btn active" onclick="cargarReporte('ingresos',this)">💰 Ingresos</button>
      <button class="tab-btn" onclick="cargarReporte('ocupacion',this)">📅 Ocupación</button>
      <button class="tab-btn" onclick="cargarReporte('servicios',this)">✂ Servicios</button>
      <button class="tab-btn" onclick="cargarReporte('clientes',this)">👥 Clientes</button>
      <button class="tab-btn" onclick="cargarReporte('cancelaciones',this)">✕ Cancelaciones</button>
    </div>
    <div id="reporte-content"></div>`;
  cargarReporte('ingresos');
});

async function cargarReporte(tipo, btn) {
  if (btn) { document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
  const rc = document.getElementById('reporte-content');
  rc.innerHTML = '<div class="flex-center" style="padding:60px"><div class="spinner spinner-dark"></div></div>';
  const endpoints = {
    ingresos:      '/reportes/ingresos-diarios',
    ocupacion:     '/reportes/ocupacion-groomers',
    servicios:     '/reportes/top-servicios',
    clientes:      '/reportes/clientes-frecuentes',
    cancelaciones: '/reportes/cancelaciones',
  };
  const res  = await Http.get(endpoints[tipo]);
  const data = res?.data || [];
  const tablas = {
    ingresos:      { cols:['Fecha','Facturas','Total del día'],                       rows: data.map(r=>[r.fecha,r.facturas,formatMoneda(r.total_dia)]) },
    ocupacion:     { cols:['Groomer','Total citas','Min. trabajados','Completadas','Canceladas'], rows: data.map(r=>[r.groomer,r.total_citas,r.minutos_trabajados||0,r.completadas,r.canceladas]) },
    servicios:     { cols:['Servicio','Total citas','Ingreso estimado'],               rows: data.map(r=>[r.nombre,r.total_citas,formatMoneda(r.ingreso_estimado)]) },
    clientes:      { cols:['Cliente','Teléfono','Total citas','Gasto total'],          rows: data.map(r=>[r.nombre,r.telefono||'—',r.total_citas,formatMoneda(r.gasto_total)]) },
    cancelaciones: { cols:['Groomer','Canceladas','No asistió','Total'],               rows: data.map(r=>[r.groomer,r.canceladas,r.no_asistio,r.total]) },
  };
  const t = tablas[tipo];
  rc.innerHTML = `
    <div class="card">
      <div class="table-wrapper">
        <table>
          <thead><tr>${t.cols.map(c=>'<th>'+c+'</th>').join('')}</tr></thead>
          <tbody>
            ${!data.length
              ? '<tr><td colspan="' + t.cols.length + '" style="text-align:center;color:var(--gray-light);padding:40px">Sin datos</td></tr>'
              : t.rows.map(r=>'<tr>'+r.map(v=>'<td>'+v+'</td>').join('')+'</tr>').join('')}
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
    <div style="max-width:480px">
      <div class="card mb-16">
        <div class="card-body">
          <h3 class="text-serif mb-16">Autenticación de dos factores (2FA)</h3>
          <p class="text-muted mb-24">Protege tu cuenta con Google Authenticator o Authy.</p>
          <div id="msg-2fa"></div>
          <button class="btn btn-primary" onclick="configurar2FA()">🔐 Configurar 2FA</button>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h3 class="text-serif mb-16">Cambiar contraseña</h3>
          <div id="msg-cambio-pass"></div>
          <div class="form-group">
            <label class="form-label">Contraseña actual</label>
            <input class="form-control" type="password" id="pass-actual">
          </div>
          <div class="form-group">
            <label class="form-label">Nueva contraseña</label>
            <input class="form-control" type="password" id="pass-nueva" placeholder="Mín. 8 chars, mayús., número, símbolo">
          </div>
          <button class="btn btn-primary" onclick="cambiarContrasena()">Actualizar contraseña</button>
        </div>
      </div>
    </div>`;
});

async function configurar2FA() {
  const res = await Http.post('/auth/2fa/setup', {});
  if (!res?.success) { Toast.error('Error al configurar 2FA'); return; }
  document.getElementById('msg-2fa').innerHTML = `
    <div class="alert alert-info mb-16">
      <div>
        <strong>URI para QR (escanea con Google Authenticator):</strong><br>
        <code style="word-break:break-all;font-size:.72rem">${res.data.otpauth_uri}</code>
        <div class="form-group mt-16">
          <label class="form-label">Código de verificación</label>
          <input class="form-control" type="text" id="code-2fa" placeholder="000000" maxlength="6" inputmode="numeric">
        </div>
        <button class="btn btn-primary btn-sm mt-8" onclick="confirmar2FA()">Confirmar y activar</button>
      </div>
    </div>`;
}

async function confirmar2FA() {
  const code = document.getElementById('code-2fa')?.value;
  if (!code) return;
  const res = await Http.post('/auth/2fa/confirm', { code });
  if (res?.success) Toast.success('2FA activado correctamente');
  else Toast.error('Código inválido');
}

async function cambiarContrasena() {
  const body = {
    password_actual: document.getElementById('pass-actual').value,
    password_nueva:  document.getElementById('pass-nueva').value,
  };
  const res = await Http.put('/auth/change-password', body);
  if (res?.success) {
    Toast.success('Contraseña actualizada');
    document.getElementById('pass-actual').value = '';
    document.getElementById('pass-nueva').value  = '';
  } else {
    document.getElementById('msg-cambio-pass').innerHTML = '<div class="alert alert-error">' + (res?.message||'Error') + '</div>';
  }
}

// ══════════════════════════════════════════════════════════════
// PERFIL CLIENTE
// ══════════════════════════════════════════════════════════════
Router.register('perfil', async () => {
  document.getElementById('page-content').innerHTML = `
    <div style="max-width:480px">
      <div class="card">
        <div class="card-body">
          <h3 class="text-serif mb-24">Mi perfil</h3>
          <div id="msg-perfil"></div>
          <div class="form-group">
            <label class="form-label">Nombre</label>
            <input class="form-control" id="perf-nombre" placeholder="Tu nombre">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input class="form-control" id="perf-tel" placeholder="70000000">
          </div>
          <div class="form-group">
            <label class="form-label">Canal de notificaciones</label>
            <select class="form-control" id="perf-canal">
              <option value="email">Email</option>
              <option value="whatsapp">WhatsApp</option>
            </select>
          </div>
          <button class="btn btn-primary" onclick="Toast.success('Perfil actualizado')">Guardar cambios</button>
        </div>
      </div>
    </div>`;
});

// ══════════════════════════════════════════════════════════════
// TIENDA
// ══════════════════════════════════════════════════════════════
Router.register('tienda', async () => {
  const res = await Http.get('/productos');
  const productos = res?.data || [];
  if (!sessionStorage.getItem('cart_token')) sessionStorage.setItem('cart_token', 'cart_' + Date.now());

  document.getElementById('page-content').innerHTML = `
    <div class="flex-between mb-24">
      <h2 class="text-serif">Tienda 🛍️</h2>
      <button class="btn btn-secondary" onclick="verCarrito()">🛒 Ver carrito</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
      ${productos.map(p => `
        <div class="card">
          <div style="background:var(--cream-dark);height:120px;border-radius:var(--radius-lg) var(--radius-lg) 0 0;display:flex;align-items:center;justify-content:center;font-size:3rem">
            ${(p.categoria||'').toLowerCase().includes('shampoo')?'🧴':(p.categoria||'').toLowerCase().includes('alimento')?'🥩':'📦'}
          </div>
          <div class="card-body">
            <div style="font-weight:600;margin-bottom:4px">${p.nombre}</div>
            <div class="text-muted text-small mb-12">${p.descripcion||''}</div>
            <div class="flex-between">
              <span style="font-family:var(--font-display);font-size:1.1rem;color:var(--sage-dark)">${formatMoneda(p.precio_base)}</span>
              <button class="btn btn-primary btn-sm" onclick="agregarCarrito(${p.id},'${p.nombre.replace("'","\\'")}',${p.precio_base})">+ Agregar</button>
            </div>
          </div>
        </div>`).join('')}
    </div>`;
});

async function agregarCarrito(prodId, nombre, precio) {
  const res = await Http.post('/carrito/agregar', {
    producto_id:   prodId,
    cantidad:      1,
    session_token: sessionStorage.getItem('cart_token'),
  });
  if (res?.success) Toast.success('"' + nombre + '" agregado al carrito');
  else Toast.error(res?.message || 'Error');
}

async function verCarrito() {
  const token = sessionStorage.getItem('cart_token');
  const res   = await Http.get('/carrito?session_token=' + token);
  const items = res?.data?.items || [];
  const total = res?.data?.total || 0;
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">🛒 Mi carrito</span>
        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
      </div>
      <div class="modal-body">
        ${!items.length ? '<p class="text-muted">Tu carrito está vacío.</p>' :
          items.map(i => `
            <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--cream-dark)">
              <div>
                <strong>${i.producto}</strong>
                <div class="text-muted text-small">x${i.cantidad} × ${formatMoneda(i.precio_unitario)}</div>
              </div>
              <div class="flex gap-8">
                <strong>${formatMoneda(i.subtotal)}</strong>
                <button class="btn btn-danger btn-sm" onclick="quitarItem(${i.id},this.closest('.flex-between'))">✕</button>
              </div>
            </div>`).join('')}
        <div class="flex-between mt-16">
          <strong>Total:</strong>
          <strong style="font-family:var(--font-display);font-size:1.2rem">${formatMoneda(total)}</strong>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Seguir comprando</button>
        <button class="btn btn-primary" onclick="pedirPorWhatsApp()">📱 Pedir por WhatsApp</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
}

async function quitarItem(itemId, rowEl) {
  await Http.delete('/carrito/item/' + itemId);
  rowEl?.remove();
}

async function pedirPorWhatsApp() {
  const token = sessionStorage.getItem('cart_token');
  const res = await Http.post('/carrito/pedido', { session_token: token, metodo_contacto: 'whatsapp' });
  if (res?.success) { window.open(res.data.whatsapp_link, '_blank'); Toast.success('¡Pedido creado!'); }
  else Toast.error(res?.message || 'Error');
}
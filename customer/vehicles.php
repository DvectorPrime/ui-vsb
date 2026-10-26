<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('customer');

$pageTitle = 'My Vehicles';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Registered Vehicles</h1>
    <p class="muted">Manage your registered vehicles for maintenance appointments.</p>
  </div>
  <button type="button" class="btn gold" id="openAddVehicleBtn">+ Add Vehicle</button>
</div>

<div id="vehiclesList">
  <div class="loading"><span class="spinner"></span> Loading vehicles…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const listContainer = document.getElementById('vehiclesList');
  const addBtn = document.getElementById('openAddVehicleBtn');

  async function loadVehicles() {
    listContainer.innerHTML = '<div class="loading"><span class="spinner"></span> Loading vehicles…</div>';
    const res = await VSB.api('api/vehicles.php');

    if (res.ok && Array.isArray(res.data) && res.data.length > 0) {
      listContainer.innerHTML = `
        <div class="grid g3">
          ${res.data.map(v => `
            <div class="card">
              <div style="display:flex; justify-content:space-between; align-items:baseline;">
                <h3>${VSB.escapeHtml(v.reg_number)}</h3>
                <span class="badge badge-approved">${v.year}</span>
              </div>
              <p class="muted" style="margin: 4px 0 16px;">${VSB.escapeHtml(v.make)} ${VSB.escapeHtml(v.model)}</p>
              <div class="actions">
                <a class="btn sm" href="<?php echo BASE_URL; ?>customer/book.php?vehicle_id=${v.vehicle_id}">Book Service</a>
                <button type="button" class="btn danger sm" data-del-id="${v.vehicle_id}" data-del-reg="${VSB.escapeHtml(v.reg_number)}">Remove</button>
              </div>
            </div>
          `).join('')}
        </div>
      `;

      // Bind delete buttons
      listContainer.querySelectorAll('[data-del-id]').forEach(btn => {
        btn.onclick = async () => {
          const vid = btn.dataset.delId;
          const reg = btn.dataset.delReg;
          const confirmed = await VSB.confirm(`Are you sure you want to remove vehicle ${reg}?`, 'Remove Vehicle');
          if (!confirmed) return;

          const delRes = await VSB.api('api/vehicles.php', {
            method: 'POST',
            body: { action: 'delete', vehicle_id: parseInt(vid, 10) }
          });

          if (delRes.ok && delRes.success) {
            VSB.toast('Vehicle removed successfully', 'ok');
            loadVehicles();
          } else {
            VSB.toast(delRes.message || 'Cannot remove vehicle with active booking records', 'err');
          }
        };
      });
    } else {
      listContainer.innerHTML = `
        <div class="card empty">
          <p>You have not registered any vehicles yet.</p>
          <button type="button" class="btn gold sm" id="emptyAddBtn">+ Add your first vehicle</button>
        </div>
      `;
      const emptyBtn = document.getElementById('emptyAddBtn');
      if (emptyBtn) emptyBtn.onclick = openAddModal;
    }
  }

  function openAddModal() {
    const curYear = new Date().getFullYear();
    VSB.modal(`
      <h3>Add New Vehicle</h3>
      <div id="modalAlert" class="alert alert-error" style="display:none;"></div>
      <form id="addVehicleForm" novalidate>
        <div class="field">
          <label for="modalReg">Registration Number / Plate</label>
          <input id="modalReg" name="reg_number" type="text" placeholder="e.g. LAG-458-XY" required style="text-transform:uppercase;">
          <div class="hint">Will be automatically formatted to uppercase</div>
        </div>
        <div class="row">
          <div class="field">
            <label for="modalMake">Make</label>
            <input id="modalMake" name="make" type="text" placeholder="Toyota" required>
          </div>
          <div class="field">
            <label for="modalModel">Model</label>
            <input id="modalModel" name="model" type="text" placeholder="Camry" required>
          </div>
        </div>
        <div class="field">
          <label for="modalYear">Year</label>
          <input id="modalYear" name="year" type="number" min="1980" max="${curYear + 1}" value="${curYear - 4}" required>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button type="submit" class="btn gold" id="saveVehicleBtn">Save Vehicle</button>
        </div>
      </form>
    `, (root, close) => {
      const vForm = document.getElementById('addVehicleForm');
      const vAlert = document.getElementById('modalAlert');
      const regInput = document.getElementById('modalReg');

      // Auto-uppercase on type
      regInput.oninput = () => { regInput.value = regInput.value.toUpperCase(); };

      vForm.onsubmit = async (e) => {
        e.preventDefault();
        vAlert.style.display = 'none';

        const payload = {
          action: 'add',
          reg_number: regInput.value.trim().toUpperCase(),
          make: document.getElementById('modalMake').value.trim(),
          model: document.getElementById('modalModel').value.trim(),
          year: parseInt(document.getElementById('modalYear').value, 10)
        };

        if (!payload.reg_number || !payload.make || !payload.model || !payload.year) {
          vAlert.textContent = 'Please fill out all fields.';
          vAlert.style.display = 'block';
          return;
        }

        const saveBtn = document.getElementById('saveVehicleBtn');
        VSB.setLoading(saveBtn, true, 'Saving…');

        const res = await VSB.api('api/vehicles.php', {
          method: 'POST',
          body: payload
        });

        VSB.setLoading(saveBtn, false);

        if (res.ok && res.success) {
          close();
          VSB.toast('Vehicle added successfully!', 'ok');
          loadVehicles();
        } else {
          vAlert.textContent = res.message || res.errors?.reg_number || 'Failed to save vehicle';
          vAlert.style.display = 'block';
        }
      };
    });
  }

  addBtn.onclick = openAddModal;
  loadVehicles();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

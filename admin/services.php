<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('admin');

$pageTitle = 'Manage Services Catalogue';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Workshop Service Catalogue</h1>
    <p class="muted">Manage available services, pricing snapshots, and active status.</p>
  </div>
  <button type="button" class="btn gold" id="addServiceBtn">+ Add New Service</button>
</div>

<div id="servicesTableContainer">
  <div class="loading"><span class="spinner"></span> Loading services catalogue…</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('servicesTableContainer');
  const addBtn = document.getElementById('addServiceBtn');
  let servicesList = [];

  async function loadServices() {
    container.innerHTML = '<div class="loading"><span class="spinner"></span> Loading services…</div>';
    const res = await VSB.api('api/admin/services.php');

    if (res.ok && Array.isArray(res.data)) {
      servicesList = res.data;
      if (servicesList.length === 0) {
        container.innerHTML = '<div class="card empty"><p>No services currently exist in the database.</p></div>';
        return;
      }

      container.innerHTML = `
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Service Name</th>
                <th>Description</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              ${servicesList.map(s => `
                <tr style="${s.is_active ? '' : 'opacity:.65; background:#fafafa;'}">
                  <td>#${s.service_id}</td>
                  <td><b>${VSB.escapeHtml(s.name)}</b></td>
                  <td style="max-width:320px;">${VSB.escapeHtml(s.description || '—')}</td>
                  <td>${s.duration_mins} mins</td>
                  <td><b>${VSB.formatMoney(s.price)}</b></td>
                  <td>
                    ${s.is_active ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-not_started">Inactive</span>'}
                  </td>
                  <td>
                    <div class="actions">
                      <button type="button" class="btn ghost sm" data-edit-id="${s.service_id}">Edit</button>
                      <button type="button" class="btn danger sm" data-del-id="${s.service_id}" data-del-name="${VSB.escapeHtml(s.name)}">
                        ${s.is_active ? 'Deactivate' : 'Delete'}
                      </button>
                    </div>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;

      // Bind edit buttons
      container.querySelectorAll('[data-edit-id]').forEach(btn => {
        btn.onclick = () => {
          const sid = parseInt(btn.dataset.editId, 10);
          const svc = servicesList.find(x => x.service_id === sid);
          if (svc) openServiceModal(svc);
        };
      });

      // Bind delete buttons
      container.querySelectorAll('[data-del-id]').forEach(btn => {
        btn.onclick = async () => {
          const sid = parseInt(btn.dataset.delId, 10);
          const sName = btn.dataset.delName;
          const confirmed = await VSB.confirm(`Delete or deactivate "${sName}"? Services with booking records will be soft-deactivated to preserve historical logs.`, 'Proceed');
          if (!confirmed) return;

          const delRes = await VSB.api('api/admin/services.php', {
            method: 'POST',
            body: { action: 'delete', service_id: sid }
          });

          if (delRes.ok && delRes.success) {
            VSB.toast(delRes.message || 'Service updated', 'ok');
            loadServices();
          } else {
            VSB.toast(delRes.message || 'Operation failed', 'err');
          }
        };
      });
    } else {
      container.innerHTML = '<p class="error-text">Failed to load workshop services.</p>';
    }
  }

  function openServiceModal(s = null) {
    const isEdit = Boolean(s && s.service_id);
    VSB.modal(`
      <h3>${isEdit ? 'Edit Service' : 'Add New Service'}</h3>
      <div id="modalSvcAlert" class="alert alert-error" style="display:none;"></div>
      <form id="serviceModalForm" novalidate>
        <div class="field">
          <label for="svcName">Service Name</label>
          <input id="svcName" name="name" type="text" value="${VSB.escapeHtml(s?.name || '')}" placeholder="e.g. Wheel Alignment" required>
        </div>

        <div class="field">
          <label for="svcDesc">Description</label>
          <textarea id="svcDesc" name="description" rows="2" placeholder="Full service description…">${VSB.escapeHtml(s?.description || '')}</textarea>
        </div>

        <div class="row">
          <div class="field">
            <label for="svcPrice">Price (₦)</label>
            <input id="svcPrice" name="price" type="number" step="0.01" min="0" value="${s ? s.price : ''}" placeholder="25000.00" required>
          </div>
          <div class="field">
            <label for="svcDuration">Duration (Minutes)</label>
            <input id="svcDuration" name="duration_mins" type="number" min="15" step="15" value="${s ? s.duration_mins : 60}" required>
          </div>
        </div>

        ${isEdit ? `
          <div class="field" style="display:flex; align-items:center; gap:8px;">
            <input id="svcActive" name="is_active" type="checkbox" ${s.is_active ? 'checked' : ''} style="width:auto; margin:0;">
            <label for="svcActive" style="margin:0; font-weight:normal;">Active (Visible to customer booking catalogue)</label>
          </div>
        ` : ''}

        <div class="modal-actions">
          <button type="button" class="btn ghost sm" data-close>Cancel</button>
          <button type="submit" class="btn gold sm" id="modalSaveSvcBtn">${isEdit ? 'Update Service' : 'Create Service'}</button>
        </div>
      </form>
    `, (root, close) => {
      const f = document.getElementById('serviceModalForm');
      const alertBox = document.getElementById('modalSvcAlert');

      f.onsubmit = async (e) => {
        e.preventDefault();
        alertBox.style.display = 'none';

        const name = document.getElementById('svcName').value.trim();
        const description = document.getElementById('svcDesc').value.trim();
        const price = parseFloat(document.getElementById('svcPrice').value);
        const duration_mins = parseInt(document.getElementById('svcDuration').value, 10);

        if (!name || isNaN(price) || price <= 0 || isNaN(duration_mins) || duration_mins <= 0) {
          alertBox.textContent = 'Please enter a valid service name, positive price, and duration.';
          alertBox.style.display = 'block';
          return;
        }

        const payload = isEdit ? {
          action: 'update',
          service_id: s.service_id,
          name,
          description,
          price,
          duration_mins,
          is_active: document.getElementById('svcActive').checked
        } : {
          action: 'create',
          name,
          description,
          price,
          duration_mins
        };

        const saveBtn = document.getElementById('modalSaveSvcBtn');
        VSB.setLoading(saveBtn, true, 'Saving…');

        const saveRes = await VSB.api('api/admin/services.php', {
          method: 'POST',
          body: payload
        });

        VSB.setLoading(saveBtn, false);

        if (saveRes.ok && saveRes.success) {
          close();
          VSB.toast(isEdit ? 'Service updated successfully!' : 'Service created successfully!', 'ok');
          loadServices();
        } else {
          alertBox.textContent = saveRes.message || 'Failed to save service.';
          alertBox.style.display = 'block';
        }
      };
    });
  }

  addBtn.onclick = () => openServiceModal();
  loadServices();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        function renderJson(value) {
            return escapeHtml(JSON.stringify(value || {}, null, 2));
        }
        async function loadHealth() {
            const grid = document.getElementById('healthGrid');
            try {
                const res = await fetch('../api/admin/get_system_health.php');
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Health API failed');
                grid.innerHTML = Object.entries(data.checks).map(([name, check]) => `
                    <div class="health-card">
                        <h3>${escapeHtml(name.replaceAll('_', ' '))}</h3>
                        <div class="${check.ok ? 'health-ok' : 'health-bad'}">${check.ok ? 'OK' : 'FAIL'}</div>
                        <div style="font-size:12px;color:#7f8c8d;margin-top:4px;">${escapeHtml(check.message)}</div>
                    </div>
                `).join('');
            } catch (err) {
                grid.innerHTML = `<div class="health-card"><h3>Lỗi</h3><div class="health-bad">${escapeHtml(err.message)}</div></div>`;
            }
        }
        async function loadLogs() {
            const channel = document.getElementById('channelSelect').value;
            const limit = document.getElementById('limitSelect').value;
            const rows = document.getElementById('logRows');
            rows.innerHTML = '<tr><td colspan="5">Đang tải...</td></tr>';
            try {
                const res = await fetch(`../api/admin/get_logs.php?channel=${encodeURIComponent(channel)}&limit=${encodeURIComponent(limit)}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Log API failed');
                if (!data.logs.length) {
                    rows.innerHTML = '<tr><td colspan="5">Chưa có log cho kênh này.</td></tr>';
                    return;
                }
                rows.innerHTML = data.logs.map(entry => `
                    <tr>
                        <td>${escapeHtml(entry.timestamp || '')}</td>
                        <td><span class="level ${escapeHtml(entry.level || 'INFO')}">${escapeHtml(entry.level || '')}</span></td>
                        <td>${escapeHtml(entry.message || '')}</td>
                        <td><pre>${renderJson(entry.context)}</pre></td>
                        <td><pre>${renderJson(entry.request)}</pre></td>
                    </tr>
                `).join('');
            } catch (err) {
                rows.innerHTML = `<tr><td colspan="5">Lỗi tải log: ${escapeHtml(err.message)}</td></tr>`;
            }
        }
        function refreshAll() {
            loadHealth();
            loadLogs();
        }
        refreshAll();


document.addEventListener('click', (event) => {
    if (event.target.closest('[data-action="refresh-all"]')) {
        refreshAll();
    }
});

document.addEventListener('change', (event) => {
    if (event.target.closest('[data-action="load-logs"]')) {
        loadLogs();
    }
});

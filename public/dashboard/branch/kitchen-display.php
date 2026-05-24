<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf};
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);

if (!$branchId && !Auth::isSuperAdmin()) {
    http_response_code(403);
    exit;
}

if (!PluginLoader::isLoaded('kitchen-display')) {
    http_response_code(403);
    exit('<p style="font-family:sans-serif;padding:24px">Plugin <strong>Kitchen Display</strong> belum aktif. Aktifkan melalui halaman <a href="' . BASE_URL . '/dashboard/super/plugins.php">Plugins</a>.</p>');
}

$csrfToken = Csrf::generate();
$apiBase   = BASE_URL . '/api/plugins/kitchen-display';

ob_start();
?>
<style>
/* ── KDS Layout ─────────────────────────────────── */
.kds-wrapper {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 72px); /* subtract topbar */
  gap: 0;
}
.kds-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 4px;
  flex-shrink: 0;
}
.kds-toolbar h2 { margin: 0; font-size: 1.1rem; }
.kds-toolbar-right { display: flex; align-items: center; gap: 10px; }
.kds-status { font-size: .8rem; color: var(--text-light); }
.kds-status.error { color: #dc3545; }

/* ── Board ──────────────────────────────────────── */
.kds-board {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 12px;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}
.kds-col {
  display: flex;
  flex-direction: column;
  border-radius: 10px;
  overflow: hidden;
  background: var(--bg-secondary, #f6f7fb);
  border: 1px solid var(--border, #dde3ec);
}
.kds-col-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  font-weight: 700;
  font-size: .9rem;
  flex-shrink: 0;
}
.kds-col--pending  .kds-col-header { background: #fff8e1; border-bottom: 2px solid #f59e0b; }
.kds-col--process  .kds-col-header { background: #fff3e0; border-bottom: 2px solid #f97316; }
.kds-col--done     .kds-col-header { background: #f0fdf4; border-bottom: 2px solid #22c55e; }

.kds-badge {
  background: var(--coffee-dark, #2c1a0e);
  color: #fff;
  border-radius: 999px;
  padding: 1px 9px;
  font-size: .75rem;
  font-weight: 700;
  min-width: 22px;
  text-align: center;
}
.kds-col--pending .kds-badge  { background: #f59e0b; }
.kds-col--process .kds-badge  { background: #f97316; }
.kds-col--done    .kds-badge  { background: #22c55e; }

.kds-cards {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ── Cards ──────────────────────────────────────── */
.kds-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid var(--border, #dde3ec);
  padding: 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  animation: kds-in .25s ease;
}
@keyframes kds-in {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}
.kds-card--pending  { border-left: 4px solid #f59e0b; }
.kds-card--processing { border-left: 4px solid #f97316; }
.kds-card--completed  { border-left: 4px solid #22c55e; opacity: .85; }

.kds-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 6px;
}
.kds-order-num {
  font-size: .8rem;
  font-weight: 700;
  color: var(--coffee-dark, #2c1a0e);
  letter-spacing: .02em;
}
.kds-elapsed {
  font-size: .72rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 999px;
  white-space: nowrap;
}
.kds-elapsed--ok   { background: #dcfce7; color: #166534; }
.kds-elapsed--warn { background: #fef9c3; color: #854d0e; }
.kds-elapsed--late { background: #fee2e2; color: #991b1b; }

.kds-customer {
  font-size: .82rem;
  font-weight: 600;
  margin-bottom: 2px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.kds-channel {
  font-size: .68rem;
  background: #e0f2fe;
  color: #0369a1;
  padding: 1px 6px;
  border-radius: 999px;
  font-weight: 600;
}
.kds-paid {
  font-size: .68rem;
  background: #dcfce7;
  color: #166534;
  padding: 1px 6px;
  border-radius: 999px;
  font-weight: 700;
}
.kds-table {
  font-size: .78rem;
  color: var(--text-mid, #5b6778);
  margin-bottom: 4px;
}
.kds-items {
  list-style: none;
  margin: 6px 0;
  padding: 0;
}
.kds-items li {
  font-size: .82rem;
  padding: 3px 0;
  border-bottom: 1px dashed #f0f0f0;
  display: flex;
  gap: 6px;
}
.kds-items li:last-child { border-bottom: none; }
.kds-qty {
  font-weight: 700;
  min-width: 22px;
  color: var(--coffee-brown, #6f4e37);
}
.kds-item-name { flex: 1; }
.kds-variant   { color: var(--text-light, #94a3b8); font-size: .75rem; }
.kds-item-note { font-size: .72rem; color: #f97316; }

.kds-order-note {
  font-size: .75rem;
  color: var(--text-mid, #5b6778);
  background: #fafafa;
  border-radius: 5px;
  padding: 4px 8px;
  margin: 6px 0 0;
  border-left: 2px solid #e2e8f0;
}

.kds-actions {
  margin-top: 10px;
  display: flex;
  gap: 6px;
}
.kds-btn {
  flex: 1;
  padding: 7px 0;
  border: none;
  border-radius: 6px;
  font-size: .8rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity .15s;
}
.kds-btn:disabled { opacity: .5; cursor: not-allowed; }
.kds-btn-process { background: #f97316; color: #fff; }
.kds-btn-process:hover:not(:disabled) { background: #ea6c0a; }
.kds-btn-done    { background: #22c55e; color: #fff; }
.kds-btn-done:hover:not(:disabled)    { background: #16a34a; }

/* ── Toast ──────────────────────────────────────── */
#kds-toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(80px);
  background: #1e293b;
  color: #fff;
  padding: 10px 22px;
  border-radius: 8px;
  font-size: .85rem;
  font-weight: 600;
  z-index: 9999;
  transition: transform .3s ease;
  pointer-events: none;
}
#kds-toast.show { transform: translateX(-50%) translateY(0); }

/* ── Empty state ────────────────────────────────── */
.kds-empty {
  text-align: center;
  padding: 32px 16px;
  color: var(--text-light, #94a3b8);
  font-size: .85rem;
}
</style>

<div class="kds-wrapper">

  <!-- Toolbar -->
  <div class="kds-toolbar">
    <h2>Kitchen Display <small style="font-size:.75rem;font-weight:400;color:var(--text-light)">— hanya order sudah bayar</small></h2>
    <div class="kds-toolbar-right">
      <span class="kds-status" id="kds-status">Memuat...</span>
      <button class="btn btn-outline" style="font-size:.8rem;padding:5px 12px"
              onclick="toggleKdsFullscreen()" id="kds-fs-btn" title="Toggle fullscreen">
        &#9974; Fullscreen
      </button>
    </div>
  </div>

  <!-- Board -->
  <div class="kds-board">

    <div class="kds-col kds-col--pending">
      <div class="kds-col-header">
        <span>🟡 Antrian</span>
        <span class="kds-badge" id="cnt-pending">0</span>
      </div>
      <div class="kds-cards" id="col-pending">
        <div class="kds-empty">Memuat...</div>
      </div>
    </div>

    <div class="kds-col kds-col--process">
      <div class="kds-col-header">
        <span>🟠 Diproses</span>
        <span class="kds-badge" id="cnt-processing">0</span>
      </div>
      <div class="kds-cards" id="col-processing">
        <div class="kds-empty">Memuat...</div>
      </div>
    </div>

    <div class="kds-col kds-col--done">
      <div class="kds-col-header">
        <span>🟢 Selesai Hari Ini</span>
        <span class="kds-badge" id="cnt-completed">0</span>
      </div>
      <div class="kds-cards" id="col-completed">
        <div class="kds-empty">Memuat...</div>
      </div>
    </div>

  </div>
</div>

<div id="kds-toast"></div>

<script>
(function () {
    const ORDERS_URL    = '<?= $apiBase ?>/orders.php';
    const UPDATE_URL    = '<?= $apiBase ?>/update-status.php';
    const CSRF_TOKEN    = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
    const POLL_INTERVAL = 10000; // ms

    let knownPendingIds = new Set();
    let firstLoad       = true;
    let pollTimer       = null;

    // ── Sound notification ───────────────────────────────────────
    function playBeep() {
        try {
            const ctx  = new (window.AudioContext || window.webkitAudioContext)();
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type            = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.35, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) { /* AudioContext not available */ }
    }

    // ── Toast ────────────────────────────────────────────────────
    let toastTimer = null;
    function showToast(msg) {
        const el = document.getElementById('kds-toast');
        if (!el) return;
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
    }

    // ── Elapsed time helpers ─────────────────────────────────────
    function elapsedLabel(seconds) {
        if (seconds < 60)   return seconds + 'd';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        return h + 'j ' + (m > 0 ? m + 'm' : '');
    }

    function elapsedClass(seconds) {
        if (seconds < 600)  return 'kds-elapsed--ok';
        if (seconds < 1200) return 'kds-elapsed--warn';
        return 'kds-elapsed--late';
    }

    // ── Card renderer ────────────────────────────────────────────
    function renderCard(order, status) {
        const elapsed = order.elapsed_seconds || 0;
        const items   = (order.items || []).map(function (it) {
            let name = escHtml(it.menu_name);
            if (it.variant_label) name += ' <span class="kds-variant">(' + escHtml(it.variant_label) + ')</span>';
            let noteHtml = it.notes ? '<span class="kds-item-note"> · ' + escHtml(it.notes) + '</span>' : '';
            return '<li><span class="kds-qty">' + escHtml(String(it.quantity)) + 'x</span>'
                 + '<span class="kds-item-name">' + name + noteHtml + '</span></li>';
        }).join('');

        const noteHtml = order.notes
            ? '<div class="kds-order-note">📝 ' + escHtml(order.notes) + '</div>'
            : '';

        const tableHtml = (order.fulfillment_type === 'table' && order.table_number)
            ? '<div class="kds-table">🪑 Meja ' + escHtml(order.table_number) + '</div>'
            : '';

        let actionsHtml = '';
        if (status === 'pending') {
            actionsHtml = '<div class="kds-actions">'
                + '<button class="kds-btn kds-btn-process" onclick="kdsUpdateStatus(' + order.id + ', \'processing\')" data-oid="' + order.id + '">▶ Mulai Proses</button>'
                + '</div>';
        } else if (status === 'processing') {
            actionsHtml = '<div class="kds-actions">'
                + '<button class="kds-btn kds-btn-done" onclick="kdsUpdateStatus(' + order.id + ', \'completed\')" data-oid="' + order.id + '">✓ Selesai</button>'
                + '</div>';
        }

        return '<div class="kds-card kds-card--' + status + '" id="kds-card-' + order.id + '">'
            + '<div class="kds-card-top">'
            +   '<span class="kds-order-num">' + escHtml(order.order_number) + '</span>'
            +   '<span class="kds-elapsed ' + elapsedClass(elapsed) + '">' + elapsedLabel(elapsed) + '</span>'
            + '</div>'
            + '<div class="kds-customer">'
            +   escHtml(order.customer_name)
            +   '<span class="kds-channel">' + escHtml(order.channel) + '</span>'
            +   '<span class="kds-paid">✓ PAID</span>'
            + '</div>'
            + tableHtml
            + '<ul class="kds-items">' + items + '</ul>'
            + noteHtml
            + actionsHtml
            + '</div>';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Column renderer ──────────────────────────────────────────
    function renderColumn(colId, orders, status) {
        const col = document.getElementById(colId);
        if (!col) return;
        if (!orders.length) {
            col.innerHTML = '<div class="kds-empty">Tidak ada order</div>';
            return;
        }
        col.innerHTML = orders.map(function (o) { return renderCard(o, status); }).join('');
    }

    // ── Fetch & diff ─────────────────────────────────────────────
    async function fetchOrders() {
        try {
            const resp = await fetch(ORDERS_URL, { cache: 'no-store' });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);

            const json = await resp.json();
            if (!json.success) throw new Error(json.message || 'API error');

            const { pending, processing, completed } = json.data;

            // Detect new pending orders
            if (!firstLoad) {
                const newIds = pending
                    .map(function (o) { return o.id; })
                    .filter(function (id) { return !knownPendingIds.has(id); });

                if (newIds.length > 0) {
                    playBeep();
                    showToast('Order baru masuk: ' + newIds.length + ' pesanan!');
                }
            }

            knownPendingIds = new Set(pending.map(function (o) { return o.id; }));
            firstLoad = false;

            renderColumn('col-pending',    pending,    'pending');
            renderColumn('col-processing', processing, 'processing');
            renderColumn('col-completed',  completed,  'completed');

            document.getElementById('cnt-pending').textContent    = pending.length;
            document.getElementById('cnt-processing').textContent = processing.length;
            document.getElementById('cnt-completed').textContent  = completed.length;

            const now = new Date();
            const hh  = String(now.getHours()).padStart(2, '0');
            const mm  = String(now.getMinutes()).padStart(2, '0');
            const ss  = String(now.getSeconds()).padStart(2, '0');
            const st  = document.getElementById('kds-status');
            st.textContent = 'Update ' + hh + ':' + mm + ':' + ss;
            st.className   = 'kds-status';

        } catch (e) {
            const st = document.getElementById('kds-status');
            st.textContent = 'Gagal memuat: ' + e.message;
            st.className   = 'kds-status error';
        }
    }

    // ── Status update ────────────────────────────────────────────
    window.kdsUpdateStatus = async function (orderId, newStatus) {
        const btns = document.querySelectorAll('[data-oid="' + orderId + '"]');
        btns.forEach(function (b) { b.disabled = true; });

        try {
            const resp = await fetch(UPDATE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ order_id: orderId, status: newStatus }),
            });

            const json = await resp.json();
            if (!json.success) {
                showToast('Gagal: ' + (json.message || 'Error'));
                btns.forEach(function (b) { b.disabled = false; });
                return;
            }

            await fetchOrders();

        } catch (e) {
            showToast('Error: ' + e.message);
            btns.forEach(function (b) { b.disabled = false; });
        }
    };

    // ── Fullscreen ───────────────────────────────────────────────
    window.toggleKdsFullscreen = function () {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(function () {});
            document.getElementById('kds-fs-btn').textContent = '✕ Exit Fullscreen';
        } else {
            document.exitFullscreen();
            document.getElementById('kds-fs-btn').innerHTML = '&#9974; Fullscreen';
        }
    };

    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            document.getElementById('kds-fs-btn').innerHTML = '&#9974; Fullscreen';
        }
    });

    // ── Auto-refresh elapsed times every minute ──────────────────
    function refreshElapsed() {
        document.querySelectorAll('.kds-card').forEach(function (card) {
            const elapsed = card.querySelector('.kds-elapsed');
            if (!elapsed) return;
            // Increment all elapsed spans by 60 seconds (rough approximation)
            const cur = parseInt(elapsed.dataset.seconds || '0', 10) + 60;
            elapsed.dataset.seconds = cur;
            elapsed.textContent     = elapsedLabel(cur);
            elapsed.className       = 'kds-elapsed ' + elapsedClass(cur);
        });
    }

    // ── Boot ─────────────────────────────────────────────────────
    fetchOrders();
    pollTimer = setInterval(fetchOrders, POLL_INTERVAL);

})();
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Kitchen Display', $content, 'branch_admin');

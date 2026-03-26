<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

$dashboard  = PluginTicketdashboardDashboard::getOrCreateDefault();
$did        = (int) $dashboard->fields['id'];
$types      = PluginTicketdashboardWidget::getWidgetTypes();

$builderUrl  = Plugin::getWebDir('ticketdashboard') . '/front/builder.php';
$dashboardUrl = Plugin::getWebDir('ticketdashboard') . '/front/dashboard.php';
$ajaxUrl     = Plugin::getWebDir('ticketdashboard') . '/ajax/builder.php';

// ── Salvar ordem (POST normal, sem widgets aninhados) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_order']) && is_array($_POST['order'])) {
    global $DB;
    foreach ($_POST['order'] as $pos => $wid) {
        $DB->update(
            'glpi_plugin_ticketdashboard_widgets',
            ['position' => (int) $pos],
            ['id' => (int) $wid, 'dashboards_id' => $did]
        );
    }
    Html::redirect($builderUrl);
}

// Carrega widgets após possíveis mudanças
$current    = PluginTicketdashboardWidget::getForDashboard($did);
$activeKeys = array_column($current, 'widget_type');

Html::header(
    __('Construtor de Dashboard', 'ticketdashboard'),
    $_SERVER['PHP_SELF'],
    'helpdesk',
    'PluginTicketdashboardDashboard'
);
?>
<div class="container-fluid mt-3" style="max-width:960px;">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">
            <i class="fas fa-tools me-2 text-primary"></i>
            <?= __('Construtor de Dashboard', 'ticketdashboard') ?>
        </h4>
        <a href="<?= $dashboardUrl ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-chart-bar me-1"></i><?= __('Ver Dashboard', 'ticketdashboard') ?>
        </a>
    </div>

    <div id="builder-alert" class="alert d-none mb-3" role="alert"></div>

    <div class="row g-3">

        <!-- Widgets disponíveis -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header py-2 fw-semibold">
                    <i class="fas fa-plus-circle me-1 text-success"></i>
                    <?= __('Widgets disponíveis', 'ticketdashboard') ?>
                </div>
                <div class="list-group list-group-flush" id="available-list">
                    <?php foreach ($types as $key => $meta):
                        $active = in_array($key, $activeKeys, true);
                    ?>
                    <div class="list-group-item d-flex align-items-center justify-content-between py-2"
                         data-type="<?= htmlspecialchars($key) ?>">
                        <div>
                            <i class="<?= $meta['icon'] ?> me-2 text-secondary"></i>
                            <span><?= htmlspecialchars($meta['label']) ?></span>
                        </div>
                        <?php if ($active): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle widget-active-badge">
                                <i class="fas fa-check me-1"></i><?= __('Ativo', 'ticketdashboard') ?>
                            </span>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-success btn-add-widget"
                                    data-type="<?= htmlspecialchars($key) ?>"
                                    data-label="<?= htmlspecialchars($meta['label']) ?>"
                                    data-icon="<?= htmlspecialchars($meta['icon']) ?>">
                                <i class="fas fa-plus me-1"></i><?= __('Adicionar', 'ticketdashboard') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Widgets ativos / reordenação -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header py-2 fw-semibold">
                    <i class="fas fa-th-list me-1 text-primary"></i>
                    <?= __('Widgets ativos', 'ticketdashboard') ?>
                    <small class="text-muted fw-normal ms-2">
                        <i class="fas fa-arrows-alt-v me-1"></i><?= __('Arraste para reordenar', 'ticketdashboard') ?>
                    </small>
                </div>

                <?php if (empty($current)): ?>
                <div class="card-body text-muted text-center py-4" id="empty-msg">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    <?= __('Nenhum widget ativo. Adicione widgets ao lado.', 'ticketdashboard') ?>
                </div>
                <?php endif; ?>

                <form method="post" action="<?= $builderUrl ?>" id="order-form">
                    <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                    <input type="hidden" name="save_order" value="1">
                    <div id="order-inputs"></div>

                    <ul id="sortable-widgets" class="list-group list-group-flush mb-0"
                        style="min-height:60px;">
                        <?php foreach ($current as $w):
                            $meta = $types[$w['widget_type']] ?? null;
                            if (!$meta) continue;
                        ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between py-2 sortable-item"
                            data-id="<?= (int) $w['id'] ?>"
                            data-type="<?= htmlspecialchars($w['widget_type']) ?>"
                            draggable="true">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-grip-vertical me-3 text-muted drag-handle" style="cursor:grab;font-size:1.1rem;"></i>
                                <i class="<?= $meta['icon'] ?> me-2 text-secondary"></i>
                                <span><?= htmlspecialchars($meta['label']) ?></span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-widget"
                                    data-id="<?= (int) $w['id'] ?>"
                                    data-type="<?= htmlspecialchars($w['widget_type']) ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($current)): ?>
                    <div class="card-footer text-end py-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i><?= __('Salvar ordem', 'ticketdashboard') ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.sortable-item.drag-over  { border-top: 3px solid #1976d2; }
.sortable-item.dragging   { opacity: .4; background: #f0f4ff; }
</style>

<script>
(function () {
    const AJAX_URL = '<?= $ajaxUrl ?>';
    const CSRF     = document.querySelector('#order-form [name="_glpi_csrf_token"]').value;

    // ── Utilitários ──────────────────────────────────────────────────────────

    function showAlert(msg, type = 'success') {
        const el = document.getElementById('builder-alert');
        el.className = `alert alert-${type}`;
        el.textContent = msg;
        el.classList.remove('d-none');
        setTimeout(() => el.classList.add('d-none'), 3000);
    }

    async function ajaxPost(data) {
        // Para AJAX o listener lê de X-Glpi-Csrf-Token + exige X-Requested-With
        const res = await fetch(AJAX_URL, {
            method: 'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': CSRF,
            },
            body: JSON.stringify(data),
        });
        return res.json();
    }

    // ── Adicionar widget ─────────────────────────────────────────────────────

    document.querySelectorAll('.btn-add-widget').forEach(btn => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                const res = await ajaxPost({ action: 'add', widget_type: btn.dataset.type });
                if (res.success) {
                    addItemToSortable(res.id, btn.dataset.type, btn.dataset.label, btn.dataset.icon);
                    // Marca como ativo no painel esquerdo
                    const row = document.querySelector(`#available-list [data-type="${btn.dataset.type}"]`);
                    if (row) {
                        btn.outerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle widget-active-badge">
                            <i class="fas fa-check me-1"></i>Ativo</span>`;
                    }
                    showAlert('Widget adicionado!');
                } else {
                    showAlert(res.error || 'Erro ao adicionar.', 'danger');
                    btn.disabled = false;
                }
            } catch (e) {
                showAlert('Erro de comunicação.', 'danger');
                btn.disabled = false;
            }
        });
    });

    function addItemToSortable(id, type, label, icon) {
        const list = document.getElementById('sortable-widgets');
        const li   = document.createElement('li');
        li.className   = 'list-group-item d-flex align-items-center justify-content-between py-2 sortable-item';
        li.dataset.id  = id;
        li.dataset.type = type;
        li.draggable   = true;
        li.innerHTML   = `
            <div class="d-flex align-items-center">
                <i class="fas fa-grip-vertical me-3 text-muted drag-handle" style="cursor:grab;font-size:1.1rem;"></i>
                <i class="${icon} me-2 text-secondary"></i>
                <span>${label}</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-widget"
                    data-id="${id}" data-type="${type}">
                <i class="fas fa-trash-alt"></i>
            </button>`;
        list.appendChild(li);
        bindDragEvents(li);
        bindRemoveEvent(li.querySelector('.btn-remove-widget'));
        updateOrderInputs();

        // Mostra botão salvar e esconde mensagem vazia
        ensureFooterVisible();
        const emptyMsg = document.getElementById('empty-msg');
        if (emptyMsg) emptyMsg.style.display = 'none';
    }

    // ── Remover widget ───────────────────────────────────────────────────────

    function bindRemoveEvent(btn) {
        btn.addEventListener('click', async () => {
            if (!confirm('Remover este widget do dashboard?')) return;
            btn.disabled = true;
            const li   = btn.closest('li');
            const type = li.dataset.type;
            try {
                const res = await ajaxPost({ action: 'remove', widget_id: li.dataset.id });
                if (res.success) {
                    li.remove();
                    updateOrderInputs();
                    // Restaura botão "Adicionar" no painel esquerdo
                    const row = document.querySelector(`#available-list [data-type="${type}"]`);
                    if (row) {
                        const badge = row.querySelector('.widget-active-badge');
                        if (badge) {
                            const icon  = row.querySelector('i.text-secondary')?.className || '';
                            const label = row.querySelector('span')?.textContent || '';
                            badge.outerHTML = `<button type="button"
                                class="btn btn-sm btn-outline-success btn-add-widget"
                                data-type="${type}"
                                data-label="${label}"
                                data-icon="${icon.split(' ').slice(0,2).join(' ')}">
                                <i class="fas fa-plus me-1"></i>Adicionar</button>`;
                            // Re-bind
                            row.querySelector('.btn-add-widget').addEventListener('click', arguments.callee);
                        }
                    }
                    showAlert('Widget removido.');
                } else {
                    showAlert(res.error || 'Erro ao remover.', 'danger');
                    btn.disabled = false;
                }
            } catch (e) {
                showAlert('Erro de comunicação.', 'danger');
                btn.disabled = false;
            }
        });
    }

    document.querySelectorAll('.btn-remove-widget').forEach(bindRemoveEvent);

    // ── Drag & Drop ──────────────────────────────────────────────────────────

    let dragged = null;

    function bindDragEvents(li) {
        li.addEventListener('dragstart', e => {
            dragged = li;
            li.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        li.addEventListener('dragend', () => {
            li.classList.remove('dragging');
            document.querySelectorAll('.sortable-item').forEach(i => i.classList.remove('drag-over'));
            dragged = null;
            updateOrderInputs();
        });

        li.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!dragged || dragged === li) return;

            document.querySelectorAll('.sortable-item').forEach(i => i.classList.remove('drag-over'));
            li.classList.add('drag-over');

            const rect = li.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;
            const list = document.getElementById('sortable-widgets');
            if (e.clientY < mid) {
                list.insertBefore(dragged, li);
            } else {
                list.insertBefore(dragged, li.nextSibling);
            }
        });

        li.addEventListener('dragleave', () => li.classList.remove('drag-over'));
        li.addEventListener('drop', e => { e.preventDefault(); });
    }

    document.querySelectorAll('.sortable-item').forEach(bindDragEvents);

    // ── Hidden inputs de ordem ───────────────────────────────────────────────

    function updateOrderInputs() {
        const container = document.getElementById('order-inputs');
        container.innerHTML = '';
        document.querySelectorAll('#sortable-widgets .sortable-item').forEach((li, idx) => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = `order[${idx}]`;
            inp.value = li.dataset.id;
            container.appendChild(inp);
        });
    }

    updateOrderInputs();

    // ── Garante rodapé visível ───────────────────────────────────────────────

    function ensureFooterVisible() {
        const form   = document.getElementById('order-form');
        let footer   = form.querySelector('.card-footer');
        if (!footer) {
            footer = document.createElement('div');
            footer.className = 'card-footer text-end py-2';
            footer.innerHTML = `<button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-save me-1"></i>Salvar ordem</button>`;
            form.appendChild(footer);
        }
    }

})();
</script>

<?php Html::footer(); ?>

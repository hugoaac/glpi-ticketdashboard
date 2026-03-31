<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

$dashboard  = PluginTicketdashboardDashboard::getOrCreateDefault();
$widgets    = PluginTicketdashboardWidget::getForDashboard($dashboard->fields['id']);
$types      = PluginTicketdashboardWidget::getWidgetTypes();
$groups     = PluginTicketdashboardDashboard::getGroupsForFilter();
$technicians = PluginTicketdashboardDashboard::getTechniciansForFilter();
$requesters  = PluginTicketdashboardDashboard::getRequestersForFilter();
$ajaxUrl    = Plugin::getWebDir('ticketdashboard') . '/ajax/data.php';
$builderUrl = Plugin::getWebDir('ticketdashboard') . '/front/builder.php';

// Período padrão: mês atual
$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-t');

// Grupo padrão: primeiro grupo do técnico logado
$defaultGroupId = PluginTicketdashboardDashboard::getUserDefaultGroupId();

Html::header(
    __('Ticket Dashboard', 'ticketdashboard'),
    $_SERVER['PHP_SELF'],
    'helpdesk',
    'PluginTicketdashboardDashboard'
);
?>
<div class="container-fluid mt-3">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">
            <i class="fas fa-chart-bar me-2 text-primary"></i>
            <?= htmlspecialchars($dashboard->fields['name']) ?>
        </h4>
        <a href="<?= $builderUrl ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-tools me-1"></i><?= __('Construtor de Dashboard', 'ticketdashboard') ?>
        </a>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('De', 'ticketdashboard') ?></label>
                    <input type="date" id="filter_date_from" class="form-control form-control-sm"
                           value="<?= $defaultFrom ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Até', 'ticketdashboard') ?></label>
                    <input type="date" id="filter_date_to" class="form-control form-control-sm"
                           value="<?= $defaultTo ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Tipo', 'ticketdashboard') ?></label>
                    <select id="filter_ticket_type" class="form-select form-select-sm">
                        <option value=""><?= __('Todos', 'ticketdashboard') ?></option>
                        <option value="1"><?= __('Incidente', 'ticketdashboard') ?></option>
                        <option value="2"><?= __('Requisição', 'ticketdashboard') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Grupo', 'ticketdashboard') ?></label>
                    <select id="filter_groups_id" class="form-select form-select-sm">
                        <option value="0"><?= __('Todos', 'ticketdashboard') ?></option>
                        <?php foreach ($groups as $gid => $gname): ?>
                            <option value="<?= (int)$gid ?>"
                                <?= $defaultGroupId === (int)$gid ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gname) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Técnico', 'ticketdashboard') ?></label>
                    <select id="filter_users_id" class="form-select form-select-sm">
                        <option value="0"><?= __('Todos', 'ticketdashboard') ?></option>
                        <?php foreach ($technicians as $uid => $uname): ?>
                            <option value="<?= (int)$uid ?>"><?= htmlspecialchars($uname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Requerente', 'ticketdashboard') ?></label>
                    <select id="filter_requester_id" class="form-select form-select-sm">
                        <option value="0"><?= __('Todos', 'ticketdashboard') ?></option>
                        <?php foreach ($requesters as $rid => $rname): ?>
                            <option value="<?= (int)$rid ?>"><?= htmlspecialchars($rname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Status', 'ticketdashboard') ?></label>
                    <select id="filter_status" class="form-select form-select-sm">
                        <option value="0"><?= __('Todos', 'ticketdashboard') ?></option>
                        <option value="1"><?= __('Novo', 'ticketdashboard') ?></option>
                        <option value="2"><?= __('Em atendimento', 'ticketdashboard') ?></option>
                        <option value="3"><?= __('Planejado', 'ticketdashboard') ?></option>
                        <option value="4"><?= __('Pendente', 'ticketdashboard') ?></option>
                        <option value="5"><?= __('Resolvido', 'ticketdashboard') ?></option>
                        <option value="6"><?= __('Fechado', 'ticketdashboard') ?></option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1"><?= __('Prioridade', 'ticketdashboard') ?></label>
                    <select id="filter_priority" class="form-select form-select-sm">
                        <option value="0"><?= __('Todas', 'ticketdashboard') ?></option>
                        <option value="1"><?= __('Muito baixa', 'ticketdashboard') ?></option>
                        <option value="2"><?= __('Baixa', 'ticketdashboard') ?></option>
                        <option value="3"><?= __('Média', 'ticketdashboard') ?></option>
                        <option value="4"><?= __('Alta', 'ticketdashboard') ?></option>
                        <option value="5"><?= __('Muito alta', 'ticketdashboard') ?></option>
                        <option value="6"><?= __('Maior', 'ticketdashboard') ?></option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1"><?= __('Auto-refresh', 'ticketdashboard') ?></label>
                    <select id="filter_refresh" class="form-select form-select-sm">
                        <option value="0"><?= __('Desligado', 'ticketdashboard') ?></option>
                        <option value="30">30s</option>
                        <option value="60">1 min</option>
                        <option value="300">5 min</option>
                        <option value="600">10 min</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button id="btn-apply" class="btn btn-primary btn-sm w-100 mt-3">
                        <i class="fas fa-search me-1"></i><?= __('Aplicar', 'ticketdashboard') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid de Widgets -->
    <div id="widget-grid" class="row g-3">
        <?php foreach ($widgets as $widget):
            $meta = $types[$widget['widget_type']] ?? null;
            if (!$meta) continue;
        ?>
        <div class="<?= $meta['size'] ?>" data-widget-type="<?= htmlspecialchars($widget['widget_type']) ?>">
            <div class="card h-100 shadow-sm">
                <div class="card-header py-2 d-flex align-items-center">
                    <i class="<?= $meta['icon'] ?> me-2 text-secondary"></i>
                    <strong><?= htmlspecialchars($meta['label']) ?></strong>
                </div>
                <?php $noMinH = in_array($widget['widget_type'], ['by_status', 'tech_origin_matrix']); ?>
                <div class="card-body d-flex align-items-center justify-content-center" style="min-height:<?= $noMinH ? '60px' : '200px' ?>;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($widgets)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <?= __('Nenhum widget configurado.', 'ticketdashboard') ?>
                <a href="<?= $builderUrl ?>"><?= __('Abrir construtor', 'ticketdashboard') ?></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const AJAX_URL       = '<?= $ajaxUrl ?>';
const chartInstances = {};

function getFilters() {
    return {
        date_from:    document.getElementById('filter_date_from').value,
        date_to:      document.getElementById('filter_date_to').value,
        ticket_type:  document.getElementById('filter_ticket_type').value,
        groups_id:    document.getElementById('filter_groups_id').value,
        priority:     document.getElementById('filter_priority').value,
        users_id:     document.getElementById('filter_users_id').value,
        requester_id: document.getElementById('filter_requester_id').value,
        status:       document.getElementById('filter_status').value,
    };
}

function buildQueryString(filters) {
    return new URLSearchParams(filters).toString();
}

async function loadWidget(el) {
    const type    = el.dataset.widgetType;
    const body    = el.querySelector('.card-body');
    const filters = getFilters();

    const params = buildQueryString({ widget_type: type, ...filters });

    body.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';

    try {
        const res  = await fetch(AJAX_URL + '?' + params);
        const data = await res.json();
        renderWidget(body, type, data);
    } catch (e) {
        body.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Erro ao carregar</div>';
    }
}

function renderWidget(body, type, data) {
    if (chartInstances[type]) {
        chartInstances[type].destroy();
        delete chartInstances[type];
    }

    if (data.type === 'number') {
        body.innerHTML = `
            <div class="text-center">
                <div style="font-size:3.5rem;font-weight:700;color:${data.color || '#1976d2'}">${data.value}</div>
                <div class="text-muted mt-1">${data.label}</div>
            </div>`;
        return;
    }

    if (data.type === 'number_pct') {
        const color = data.color || '#1976d2';
        body.innerHTML = `
            <div class="text-center">
                <div style="font-size:3rem;font-weight:700;color:${color}">${data.value}</div>
                <div class="my-2">
                    <span class="badge rounded-pill px-3 py-2" style="background:${color};font-size:.95rem;">${data.pct}%</span>
                </div>
                <div class="text-muted small">${data.label} do total</div>
            </div>`;
        return;
    }

    if (data.type === 'status_cards') {
        body.style.minHeight = 'auto';
        body.classList.remove('d-flex', 'align-items-center', 'justify-content-center');
        body.innerHTML = `
            <div class="row g-3 p-1">
                ${data.cards.map(card => `
                <div class="col-6 col-xl-3">
                    <div class="rounded-3 p-3 text-white d-flex align-items-center gap-3"
                         style="background:${card.color};">
                        <i class="${card.icon} fa-2x opacity-75"></i>
                        <div>
                            <div style="font-size:2rem;font-weight:700;line-height:1">${card.value}</div>
                            <div class="small opacity-90">${card.label}</div>
                        </div>
                    </div>
                </div>`).join('')}
            </div>`;
        return;
    }

    if (data.type === 'matrix_table') {
        body.classList.remove('d-flex', 'align-items-center', 'justify-content-center');
        body.style.minHeight = 'auto';
        body.style.overflowX = 'auto';
        body.style.padding   = '0';

        const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

        const headers = data.headers.map(h => `<th class="text-center small fw-semibold px-2">${esc(h)}</th>`).join('');
        const rows    = data.rows.map(r => {
            const cells = r.cells.map(c => `<td class="text-center small px-2">${c > 0 ? c : '<span class="text-muted">—</span>'}</td>`).join('');
            return `<tr><td class="small fw-medium px-2 text-nowrap">${esc(r.name)}</td>${cells}<td class="text-center small fw-semibold px-2">${r.total}</td></tr>`;
        }).join('');
        const tr = data.total_row;
        const totalCells = tr.cells.map(c => `<td class="text-center small fw-bold px-2">${c}</td>`).join('');
        const totalRow   = `<tr class="table-active fw-bold"><td class="small px-2">Total</td>${totalCells}<td class="text-center small fw-bold px-2">${tr.total}</td></tr>`;

        body.innerHTML = `
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:.82rem;">
                <thead class="table-light">
                    <tr>
                        <th class="px-2 small fw-semibold">Técnico</th>
                        ${headers}
                        <th class="text-center small fw-semibold px-2">Total</th>
                    </tr>
                </thead>
                <tbody>${rows}${totalRow}</tbody>
            </table>`;
        return;
    }

    if (data.type === 'empty') {
        body.innerHTML = '<div class="text-muted text-center">Sem dados no período</div>';
        return;
    }

    if (data.error) {
        body.innerHTML = `<div class="text-danger">${data.error}</div>`;
        return;
    }

    // Gráficos de barra (simples ou empilhado)
    const canvas = document.createElement('canvas');
    body.innerHTML = '';
    body.appendChild(canvas);

    const isStacked = data.type === 'stacked_bar';

    chartInstances[type] = new Chart(canvas, {
        type: 'bar',
        data: {
            labels:   data.labels,
            datasets: data.datasets,
        },
        options: {
            indexAxis: isStacked ? 'x' : 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: isStacked },
            },
            scales: {
                x: { stacked: isStacked },
                y: { stacked: isStacked },
            },
        },
    });

    canvas.parentElement.style.height = '240px';
}

function loadAllWidgets() {
    document.querySelectorAll('[data-widget-type]').forEach(loadWidget);
}

let refreshTimer = null;

function applyRefresh() {
    clearInterval(refreshTimer);
    const seconds = parseInt(document.getElementById('filter_refresh').value, 10);
    if (seconds > 0) {
        refreshTimer = setInterval(loadAllWidgets, seconds * 1000);
    }
}

document.getElementById('btn-apply').addEventListener('click', () => {
    loadAllWidgets();
    applyRefresh();
});

document.getElementById('filter_refresh').addEventListener('change', applyRefresh);

document.addEventListener('DOMContentLoaded', loadAllWidgets);
</script>

<?php Html::footer(); ?>

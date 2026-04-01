<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

$dashboard  = PluginTicketdashboardDashboard::getOrCreateDefault();
$widgets    = PluginTicketdashboardWidget::getForDashboard($dashboard->fields['id']);
$types      = PluginTicketdashboardWidget::getWidgetTypes();
$groups     = PluginTicketdashboardDashboard::getGroupsForFilter();
$technicians = PluginTicketdashboardDashboard::getTechniciansForFilter();
$requesters  = PluginTicketdashboardDashboard::getRequestersForFilter();
$authors     = PluginTicketdashboardDashboard::getAuthorsForFilter();
$ajaxUrl     = Plugin::getWebDir('ticketdashboard') . '/ajax/data.php';
$ticketsUrl  = Plugin::getWebDir('ticketdashboard') . '/ajax/tickets.php';
$builderUrl  = Plugin::getWebDir('ticketdashboard') . '/front/builder.php';

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
                    <label class="form-label form-label-sm mb-1"><?= __('Requerente - Requerente', 'ticketdashboard') ?></label>
                    <select id="filter_requester_id" class="form-select form-select-sm">
                        <option value="0"><?= __('Todos', 'ticketdashboard') ?></option>
                        <?php foreach ($requesters as $rid => $rname): ?>
                            <option value="<?= (int)$rid ?>"><?= htmlspecialchars($rname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1"><?= __('Requerente - Autor', 'ticketdashboard') ?></label>
                    <select id="filter_author_id" class="form-select form-select-sm">
                        <option value="0"><?= __('Todos', 'ticketdashboard') ?></option>
                        <?php foreach ($authors as $aid => $aname): ?>
                            <option value="<?= (int)$aid ?>"><?= htmlspecialchars($aname) ?></option>
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

<!-- Modal Detalhamento de Chamados -->
<div class="modal fade" id="drilldown-modal" tabindex="-1" aria-labelledby="drilldown-modal-label">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="drilldown-modal-label">
                    <i class="fas fa-list-alt me-2 text-primary"></i>
                    <span id="drilldown-modal-title">Detalhamento de Chamados</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="drilldown-modal-body">
                <div class="d-flex justify-content-center p-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer py-1 gap-1">
                <small class="text-muted me-auto" id="drilldown-modal-count"></small>
                <button id="btn-export-csv" class="btn btn-outline-success btn-sm" disabled onclick="exportDrillCSV()">
                    <i class="fas fa-file-csv me-1"></i>CSV
                </button>
                <button id="btn-export-xlsx" class="btn btn-outline-success btn-sm" disabled onclick="exportDrillXLSX()">
                    <i class="fas fa-file-excel me-1"></i>XLSX
                </button>
                <button id="btn-export-pdf" class="btn btn-outline-danger btn-sm" disabled onclick="exportDrillPDF()">
                    <i class="fas fa-file-pdf me-1"></i>PDF
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i><?= __('Fechar', 'ticketdashboard') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
const AJAX_URL    = '<?= $ajaxUrl ?>';
const TICKETS_URL = '<?= $ticketsUrl ?>';
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
        author_id:    document.getElementById('filter_author_id').value,
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

        const esc  = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const attr = s => String(s).replace(/"/g,'&quot;');

        const drillCell = (count, techId, originId, techName, originName, bold) =>
            count > 0
                ? `<td class="text-center small px-2 drill-cell${bold ? ' fw-bold' : ' fw-semibold'}"
                       style="cursor:pointer;color:#1976d2"
                       data-tech-id="${techId}" data-origin-id="${originId}"
                       data-tech-name="${attr(techName)}" data-origin-name="${attr(originName)}">${count}</td>`
                : `<td class="text-center small px-2${bold ? ' fw-bold' : ''}"><span class="text-muted">—</span></td>`;

        const headers = data.headers.map(h => `<th class="text-center small fw-semibold px-2">${esc(h)}</th>`).join('');
        const rows    = data.rows.map(r => {
            const cells = r.cells.map((c, i) =>
                drillCell(c, r.tech_id, data.origin_ids[i], r.name, data.headers[i], false)
            ).join('');
            const total = drillCell(r.total, r.tech_id, 0, r.name, 'Todas', false);
            return `<tr><td class="small fw-medium px-2 text-nowrap">${esc(r.name)}</td>${cells}${total}</tr>`;
        }).join('');
        const tr         = data.total_row;
        const totalCells = tr.cells.map((c, i) =>
            drillCell(c, 0, data.origin_ids[i], 'Todos', data.headers[i], true)
        ).join('');
        const grandTotal = drillCell(tr.total, 0, 0, 'Todos', 'Todas', true);
        const totalRow   = `<tr class="table-active fw-bold"><td class="small px-2">Total</td>${totalCells}${grandTotal}</tr>`;

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

// ── Drill-down: clique nas células da matriz ──────────────────────────────────

let currentDrillData  = [];
let currentDrillTitle = '';

const DRILL_HEADERS = ['ID', 'Título', 'Nome Operador', 'Data Criação', 'Data Conclusão', 'Origem', 'Status'];
const DRILL_FIELDS  = ['id', 'name', 'tech', 'date', 'closedate', 'origin', 'status'];

document.addEventListener('click', function (e) {
    const cell = e.target.closest('.drill-cell');
    if (!cell) return;
    openDrillDown(
        cell.dataset.techId,
        cell.dataset.originId,
        cell.dataset.techName,
        cell.dataset.originName
    );
});

function setExportButtons(enabled) {
    ['btn-export-csv', 'btn-export-xlsx', 'btn-export-pdf'].forEach(id => {
        document.getElementById(id).disabled = !enabled;
    });
}

function drillFilename(ext) {
    const safe = currentDrillTitle
        .replace(/[^a-zA-Z0-9À-ÿ ×\-]/g, '')
        .trim()
        .replace(/[ ×\-]+/g, '_');
    return 'chamados_' + (safe || 'todos') + '.' + ext;
}

async function openDrillDown(techId, originId, techName, originName) {
    const modalEl = document.getElementById('drilldown-modal');
    const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);

    // Título dinâmico
    const parts = [];
    if (techName   && techName   !== 'Todos') parts.push(techName);
    if (originName && originName !== 'Todas') parts.push(originName);
    currentDrillTitle = parts.join(' × ') || 'Todos os chamados';
    document.getElementById('drilldown-modal-title').textContent =
        'Detalhamento — ' + currentDrillTitle;

    // Reset
    currentDrillData = [];
    setExportButtons(false);
    document.getElementById('drilldown-modal-body').innerHTML =
        '<div class="d-flex justify-content-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
    document.getElementById('drilldown-modal-count').textContent = '';

    modal.show();

    try {
        const params = new URLSearchParams({
            ...getFilters(),
            drill_tech_id:   techId,
            drill_origin_id: originId,
        });
        const res  = await fetch(TICKETS_URL + '?' + params);
        const data = await res.json();
        renderDrillDown(data);
    } catch (err) {
        document.getElementById('drilldown-modal-body').innerHTML =
            '<div class="text-danger p-3"><i class="fas fa-exclamation-circle me-1"></i>Erro ao carregar dados.</div>';
    }
}

function renderDrillDown(data) {
    const body = document.getElementById('drilldown-modal-body');

    if (!data.tickets || data.tickets.length === 0) {
        body.innerHTML = '<div class="text-muted text-center p-4">Nenhum chamado encontrado.</div>';
        document.getElementById('drilldown-modal-count').textContent = '0 chamados';
        return;
    }

    currentDrillData = data.tickets;
    setExportButtons(true);

    const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    const statusClass = {
        'Novo':            'text-primary',
        'Em atendimento':  'text-warning',
        'Planejado':       'text-info',
        'Pendente':        'text-secondary',
        'Resolvido':       'text-success',
        'Fechado':         'text-muted',
    };

    const rows = data.tickets.map(t => `
        <tr>
            <td class="small px-2 text-nowrap fw-semibold">${esc(String(t.id))}</td>
            <td class="small px-2">${esc(t.name)}</td>
            <td class="small px-2 text-nowrap">${esc(t.tech)}</td>
            <td class="small px-2 text-nowrap">${esc(t.date)}</td>
            <td class="small px-2 text-nowrap">${esc(t.closedate)}</td>
            <td class="small px-2 text-nowrap">${esc(t.origin)}</td>
            <td class="small px-2 text-nowrap ${statusClass[t.status] || ''}">${esc(t.status)}</td>
        </tr>`).join('');

    body.innerHTML = `
        <table class="table table-sm table-hover table-bordered mb-0" style="font-size:.82rem;">
            <thead class="table-light sticky-top">
                <tr>
                    <th class="px-2 small">ID</th>
                    <th class="px-2 small">Título</th>
                    <th class="px-2 small">Nome Operador</th>
                    <th class="px-2 small">Data Criação</th>
                    <th class="px-2 small">Data Conclusão</th>
                    <th class="px-2 small">Origem</th>
                    <th class="px-2 small">Status</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;

    const n = data.total;
    document.getElementById('drilldown-modal-count').textContent =
        n + ' chamado' + (n !== 1 ? 's' : '');
}

// ── Exportações ───────────────────────────────────────────────────────────────

function exportDrillCSV() {
    const escape = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
    const lines  = [
        DRILL_HEADERS.map(escape).join(','),
        ...currentDrillData.map(t => DRILL_FIELDS.map(f => escape(t[f])).join(',')),
    ];
    const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href: url, download: drillFilename('csv') });
    a.click();
    URL.revokeObjectURL(url);
}

function exportDrillXLSX() {
    const wsData = [
        DRILL_HEADERS,
        ...currentDrillData.map(t => DRILL_FIELDS.map(f => t[f] ?? '')),
    ];
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Larguras de coluna aproximadas
    ws['!cols'] = [8, 60, 30, 16, 20, 14, 16].map(w => ({ wch: w }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Chamados');
    XLSX.writeFile(wb, drillFilename('xlsx'));
}

function exportDrillPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('Detalhamento — ' + currentDrillTitle, 14, 14);

    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.text(currentDrillData.length + ' chamado(s)', 14, 20);

    doc.autoTable({
        startY: 24,
        head:   [DRILL_HEADERS],
        body:   currentDrillData.map(t => DRILL_FIELDS.map(f => t[f] ?? '')),
        styles:          { fontSize: 7, cellPadding: 2 },
        headStyles:      { fillColor: [25, 118, 210], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 245, 245] },
        columnStyles: {
            0: { cellWidth: 16 },
            1: { cellWidth: 80 },
            2: { cellWidth: 40 },
            3: { cellWidth: 22 },
            4: { cellWidth: 28 },
            5: { cellWidth: 22 },
            6: { cellWidth: 22 },
        },
    });

    doc.save(drillFilename('pdf'));
}
</script>

<?php Html::footer(); ?>

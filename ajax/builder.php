<?php

/**
 * Ticket Dashboard — ajax/builder.php
 * Endpoint AJAX para add/remove de widgets no construtor.
 */

include('../../../inc/includes.php');
Session::checkLoginUser();

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

$dashboard = PluginTicketdashboardDashboard::getOrCreateDefault();
$did       = (int) $dashboard->fields['id'];

if ($action === 'add') {
    $type  = $body['widget_type'] ?? '';
    $types = PluginTicketdashboardWidget::getWidgetTypes();

    if (!isset($types[$type])) {
        echo json_encode(['success' => false, 'error' => 'Tipo de widget inválido']);
        exit;
    }

    $ok = PluginTicketdashboardWidget::addToDashboard($did, $type);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => 'Widget já existe no dashboard']);
        exit;
    }

    // Retorna o ID do widget recém-criado
    global $DB;
    $row = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => 'glpi_plugin_ticketdashboard_widgets',
        'WHERE'  => ['dashboards_id' => $did, 'widget_type' => $type],
        'ORDER'  => 'id DESC',
        'LIMIT'  => 1,
    ])->current();

    echo json_encode(['success' => true, 'id' => (int) ($row['id'] ?? 0)]);
    exit;
}

if ($action === 'remove') {
    $widgetId = (int) ($body['widget_id'] ?? 0);

    if ($widgetId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    $widget = new PluginTicketdashboardWidget();
    $ok = $widget->delete(['id' => $widgetId, 'dashboards_id' => $did]);

    echo json_encode(['success' => (bool) $ok]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Ação inválida']);

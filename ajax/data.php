<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

header('Content-Type: application/json');

$widget_type = $_GET['widget_type'] ?? '';
$filters     = [
    'date_from'    => $_GET['date_from']    ?? '',
    'date_to'      => $_GET['date_to']      ?? '',
    'ticket_type'  => $_GET['ticket_type']  ?? '',
    'groups_id'    => $_GET['groups_id']    ?? 0,
    'priority'     => $_GET['priority']     ?? 0,
    'users_id'     => $_GET['users_id']     ?? [],
    'requester_id' => $_GET['requester_id'] ?? 0,
    'author_id'    => $_GET['author_id']    ?? 0,
    'status'       => $_GET['status']       ?? 0,
];

if (empty($widget_type)) {
    echo json_encode(['error' => 'widget_type obrigatório']);
    exit;
}

$data = PluginTicketdashboardDataProvider::getData($widget_type, $filters);
echo json_encode($data);

<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

header('Content-Type: application/json');

$filters = [
    'date_from'       => $_GET['date_from']       ?? '',
    'date_to'         => $_GET['date_to']         ?? '',
    'ticket_type'     => $_GET['ticket_type']     ?? '',
    'groups_id'       => $_GET['groups_id']       ?? 0,
    'priority'        => $_GET['priority']        ?? 0,
    'users_id'        => $_GET['users_id']        ?? [],
    'requester_id'    => $_GET['requester_id']    ?? 0,
    'author_id'       => $_GET['author_id']       ?? 0,
    'status'          => $_GET['status']          ?? 0,
    'drill_tech_id'   => $_GET['drill_tech_id']   ?? 0,
    'drill_origin_id' => $_GET['drill_origin_id'] ?? 0,
];

echo json_encode(PluginTicketdashboardDataProvider::getTicketDetails($filters));

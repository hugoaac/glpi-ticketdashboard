<?php

define('PLUGIN_TICKETDASHBOARD_VERSION', '1.0.0');
define('PLUGIN_TICKETDASHBOARD_MIN_GLPI', '11.0.0');
define('PLUGIN_TICKETDASHBOARD_MAX_GLPI', '11.99.99');

function plugin_version_ticketdashboard(): array
{
    return [
        'name'         => 'Ticket Dashboard',
        'version'      => PLUGIN_TICKETDASHBOARD_VERSION,
        'author'       => 'Hugo',
        'license'      => 'GPLv2+',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_TICKETDASHBOARD_MIN_GLPI,
                'max' => PLUGIN_TICKETDASHBOARD_MAX_GLPI,
            ],
            'php'  => ['min' => '8.2'],
        ],
    ];
}

function plugin_ticketdashboard_check_prerequisites(): bool
{
    return true;
}

function plugin_ticketdashboard_check_config(): bool
{
    return true;
}

function plugin_init_ticketdashboard(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['ticketdashboard'] = true;

    $PLUGIN_HOOKS['menu_toadd']['ticketdashboard'] = [
        'helpdesk' => 'PluginTicketdashboardDashboard',
    ];
}

<?php

class PluginTicketdashboardWidget extends CommonDBTM
{
    // Tipos de widget disponíveis
    public const TYPE_TOTAL       = 'total_tickets';
    public const TYPE_INCIDENTS   = 'total_incidents';
    public const TYPE_REQUESTS    = 'total_requests';
    public const TYPE_BY_GROUP    = 'by_group';
    public const TYPE_BY_TECH     = 'by_technician';
    public const TYPE_SLA         = 'sla_compliance';
    public const TYPE_TIT         = 'tit_compliance';
    public const TYPE_BY_STATUS   = 'by_status';
    public const TYPE_BY_ORIGIN   = 'by_origin';
    public const TYPE_TECH_ORIGIN    = 'tech_origin_matrix';
    public const TYPE_AUTHOR_ORIGIN  = 'author_origin_matrix';

    public static $rightname = 'ticket';

    public static function getTypeName($nb = 0): string
    {
        return __('Widget', 'ticketdashboard');
    }

    /**
     * Retorna todos os tipos de widget disponíveis com metadados.
     */
    public static function getWidgetTypes(): array
    {
        return [
            self::TYPE_TOTAL => [
                'label' => __('Total de Chamados', 'ticketdashboard'),
                'icon'  => 'fas fa-ticket-alt',
                'size'  => 'col-md-4',
                'chart' => 'number',
            ],
            self::TYPE_INCIDENTS => [
                'label' => __('Incidentes', 'ticketdashboard'),
                'icon'  => 'fas fa-exclamation-triangle',
                'size'  => 'col-md-4',
                'chart' => 'number_pct',
            ],
            self::TYPE_REQUESTS => [
                'label' => __('Requisições', 'ticketdashboard'),
                'icon'  => 'fas fa-clipboard-list',
                'size'  => 'col-md-4',
                'chart' => 'number_pct',
            ],
            self::TYPE_BY_GROUP => [
                'label' => __('Chamados por Grupo', 'ticketdashboard'),
                'icon'  => 'fas fa-users',
                'size'  => 'col-md-6',
                'chart' => 'bar',
            ],
            self::TYPE_BY_TECH => [
                'label' => __('Chamados por Técnico', 'ticketdashboard'),
                'icon'  => 'fas fa-user-cog',
                'size'  => 'col-md-6',
                'chart' => 'bar',
            ],
            self::TYPE_SLA => [
                'label' => __('SLA — Tempo de Solução', 'ticketdashboard'),
                'icon'  => 'fas fa-stopwatch',
                'size'  => 'col-md-6',
                'chart' => 'stacked',
            ],
            self::TYPE_TIT => [
                'label' => __('TIT — Tempo de Atendimento', 'ticketdashboard'),
                'icon'  => 'fas fa-hourglass-half',
                'size'  => 'col-md-6',
                'chart' => 'stacked',
            ],
            self::TYPE_BY_STATUS => [
                'label' => __('Chamados por Status', 'ticketdashboard'),
                'icon'  => 'fas fa-layer-group',
                'size'  => 'col-md-12',
                'chart' => 'status_cards',
            ],
            self::TYPE_BY_ORIGIN => [
                'label' => __('Chamados por Origem', 'ticketdashboard'),
                'icon'  => 'fas fa-random',
                'size'  => 'col-md-6',
                'chart' => 'bar',
            ],
            self::TYPE_TECH_ORIGIN => [
                'label' => __('Técnico × Origem', 'ticketdashboard'),
                'icon'  => 'fas fa-table',
                'size'  => 'col-md-12',
                'chart' => 'matrix_table',
            ],
            self::TYPE_AUTHOR_ORIGIN => [
                'label' => __('Autor × Origem', 'ticketdashboard'),
                'icon'  => 'fas fa-table',
                'size'  => 'col-md-12',
                'chart' => 'matrix_table',
            ],
        ];
    }

    /**
     * Retorna os widgets de um dashboard, ordenados por posição.
     */
    public static function getForDashboard(int $dashboards_id): array
    {
        global $DB;

        $widgets = [];
        $iter = $DB->request([
            'FROM'    => 'glpi_plugin_ticketdashboard_widgets',
            'WHERE'   => ['dashboards_id' => $dashboards_id],
            'ORDERBY' => 'position ASC',
        ]);

        foreach ($iter as $row) {
            $widgets[] = $row;
        }

        return $widgets;
    }

    /**
     * Adiciona widgets padrão ao criar um novo dashboard.
     */
    public static function addDefaults(int $dashboards_id): void
    {
        $defaults = [
            self::TYPE_BY_STATUS,
            self::TYPE_TOTAL,
            self::TYPE_INCIDENTS,
            self::TYPE_REQUESTS,
            self::TYPE_BY_GROUP,
            self::TYPE_BY_TECH,
            self::TYPE_SLA,
            self::TYPE_TIT,
        ];

        $widget = new self();
        foreach ($defaults as $pos => $type) {
            $widget->add([
                'dashboards_id' => $dashboards_id,
                'widget_type'   => $type,
                'position'      => $pos,
                'date_mod'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Adiciona um widget a um dashboard.
     */
    public static function addToDashboard(int $dashboards_id, string $type): bool
    {
        global $DB;

        // Verifica se já existe
        $existing = $DB->request([
            'FROM'  => 'glpi_plugin_ticketdashboard_widgets',
            'WHERE' => ['dashboards_id' => $dashboards_id, 'widget_type' => $type],
        ]);

        if ($existing->numrows() > 0) {
            return false;
        }

        // Posição = último + 1
        $last = $DB->request([
            'SELECT' => ['MAX' => 'position AS max_pos'],
            'FROM'   => 'glpi_plugin_ticketdashboard_widgets',
            'WHERE'  => ['dashboards_id' => $dashboards_id],
        ])->current();

        $pos = ($last['max_pos'] ?? -1) + 1;

        $widget = new self();
        return (bool) $widget->add([
            'dashboards_id' => $dashboards_id,
            'widget_type'   => $type,
            'position'      => $pos,
            'date_mod'      => date('Y-m-d H:i:s'),
        ]);
    }
}

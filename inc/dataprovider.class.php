<?php

use Glpi\DBAL\QueryExpression;

class PluginTicketdashboardDataProvider
{
    private const STATUS_NEW      = 1;
    private const STATUS_ASSIGNED = 2;
    private const STATUS_PLANNED  = 3;
    private const STATUS_WAITING  = 4;
    private const STATUS_SOLVED   = 5;
    private const STATUS_CLOSED   = 6;

    public static function getData(string $widget_type, array $filters): array
    {
        return match ($widget_type) {
            PluginTicketdashboardWidget::TYPE_TOTAL     => self::getTotalTickets($filters),
            PluginTicketdashboardWidget::TYPE_INCIDENTS => self::getTypeCount($filters, 1),
            PluginTicketdashboardWidget::TYPE_REQUESTS  => self::getTypeCount($filters, 2),
            PluginTicketdashboardWidget::TYPE_BY_GROUP  => self::getByGroup($filters),
            PluginTicketdashboardWidget::TYPE_BY_TECH   => self::getByTechnician($filters),
            PluginTicketdashboardWidget::TYPE_SLA       => self::getSLACompliance($filters),
            PluginTicketdashboardWidget::TYPE_TIT       => self::getTITCompliance($filters),
            PluginTicketdashboardWidget::TYPE_BY_STATUS   => self::getByStatus($filters),
            PluginTicketdashboardWidget::TYPE_BY_ORIGIN   => self::getByOrigin($filters),
            PluginTicketdashboardWidget::TYPE_TECH_ORIGIN    => self::getTechOriginMatrix($filters),
            PluginTicketdashboardWidget::TYPE_AUTHOR_ORIGIN  => self::getAuthorOriginMatrix($filters),
            default                                          => ['error' => 'Widget type inválido'],
        };
    }

    // -------------------------------------------------------------------------
    // Total de chamados
    // -------------------------------------------------------------------------

    private static function getTotalTickets(array $filters): array
    {
        global $DB;

        $where = self::buildWhere($filters);

        $params = [
            'SELECT' => [new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`')],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => $where,
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        $row = $DB->request($params)->current();

        return [
            'type'  => 'number',
            'value' => (int) ($row['total'] ?? 0),
            'label' => __('Total de Chamados', 'ticketdashboard'),
            'color' => '#1976d2',
        ];
    }

    // -------------------------------------------------------------------------
    // Incidentes / Requisições (com percentual sobre o total)
    // -------------------------------------------------------------------------

    /**
     * @param int $ticketType  1 = Incidente, 2 = Requisição
     */
    private static function getTypeCount(array $filters, int $ticketType): array
    {
        global $DB;

        // Total geral (sem filtro de tipo)
        $whereTotal = self::buildWhere($filters);
        $paramsTotal = [
            'SELECT' => [new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`')],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => $whereTotal,
        ];
        self::applyGroupJoin($paramsTotal, $filters);
        self::applyTechnicianJoin($paramsTotal, $filters);
        self::applyRequesterJoin($paramsTotal, $filters);
        $total = (int) ($DB->request($paramsTotal)->current()['total'] ?? 0);

        // Total do tipo específico
        $whereType = self::buildWhere($filters);
        $whereType['glpi_tickets.type'] = $ticketType;
        $paramsType = [
            'SELECT' => [new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`')],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => $whereType,
        ];
        self::applyGroupJoin($paramsType, $filters);
        self::applyTechnicianJoin($paramsType, $filters);
        self::applyRequesterJoin($paramsType, $filters);
        $count = (int) ($DB->request($paramsType)->current()['total'] ?? 0);

        $pct = $total > 0 ? round($count / $total * 100, 1) : 0;

        $isIncident = $ticketType === 1;

        return [
            'type'  => 'number_pct',
            'value' => $count,
            'pct'   => $pct,
            'label' => $isIncident
                ? __('Incidentes', 'ticketdashboard')
                : __('Requisições', 'ticketdashboard'),
            'color' => $isIncident ? '#e53935' : '#43a047',
        ];
    }

    // -------------------------------------------------------------------------
    // Chamados por grupo
    // -------------------------------------------------------------------------

    private static function getByGroup(array $filters): array
    {
        global $DB;

        // Para este widget não filtramos por grupo (mostramos todos)
        $where = self::buildWhere($filters, false);

        $params = [
            'SELECT' => [
                'glpi_groups.name AS group_name',
                new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`'),
            ],
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_groups_tickets AS gt_w' => [
                    'ON' => [
                        'glpi_tickets'  => 'id',
                        'gt_w'          => 'tickets_id',
                        [
                            'AND' => ['gt_w.type' => 2],
                        ],
                    ],
                ],
                'glpi_groups' => [
                    'ON' => [
                        'glpi_groups' => 'id',
                        'gt_w'        => 'groups_id',
                    ],
                ],
            ],
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_groups.id', 'glpi_groups.name'],
            'ORDER'   => ['total DESC'],
            'LIMIT'   => 15,
        ];

        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($DB->request($params) as $row) {
            $labels[] = $row['group_name'] ?? __('Sem grupo', 'ticketdashboard');
            $values[] = (int) $row['total'];
            $colors[] = self::seedColor($row['group_name'] ?? 'none');
        }

        if (empty($labels)) {
            return ['type' => 'empty', 'label' => __('Chamados por Grupo', 'ticketdashboard')];
        }

        return [
            'type'     => 'bar',
            'labels'   => $labels,
            'datasets' => [[
                'label'           => __('Chamados', 'ticketdashboard'),
                'data'            => $values,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    // -------------------------------------------------------------------------
    // Chamados por técnico
    // -------------------------------------------------------------------------

    private static function getByTechnician(array $filters): array
    {
        global $DB;

        $where = self::buildWhere($filters);

        $params = [
            'SELECT' => [
                new QueryExpression(
                    "TRIM(CONCAT(COALESCE(`glpi_users`.`firstname`,''), ' ', COALESCE(`glpi_users`.`realname`,''))) AS `tech_name`"
                ),
                new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`'),
            ],
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_tickets_users AS tu_w' => [
                    'ON' => [
                        'glpi_tickets' => 'id',
                        'tu_w'         => 'tickets_id',
                        [
                            'AND' => ['tu_w.type' => 2],
                        ],
                    ],
                ],
                'glpi_users' => [
                    'ON' => [
                        'glpi_users' => 'id',
                        'tu_w'       => 'users_id',
                    ],
                ],
            ],
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_users.id'],
            'ORDER'   => ['total DESC'],
            'LIMIT'   => 15,
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($DB->request($params) as $row) {
            $name     = $row['tech_name'] ?: __('Não atribuído', 'ticketdashboard');
            $labels[] = $name;
            $values[] = (int) $row['total'];
            $colors[] = self::seedColor($name);
        }

        if (empty($labels)) {
            return ['type' => 'empty', 'label' => __('Chamados por Técnico', 'ticketdashboard')];
        }

        return [
            'type'     => 'bar',
            'labels'   => $labels,
            'datasets' => [[
                'label'           => __('Chamados', 'ticketdashboard'),
                'data'            => $values,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    // -------------------------------------------------------------------------
    // Chamados por Status
    // -------------------------------------------------------------------------

    private static function getByStatus(array $filters): array
    {
        global $DB;

        $statusGroups = [
            'new'        => [self::STATUS_NEW],
            'attending'  => [self::STATUS_ASSIGNED, self::STATUS_PLANNED],
            'pending'    => [self::STATUS_WAITING],
            'solved'     => [self::STATUS_SOLVED, self::STATUS_CLOSED],
        ];

        $cards = [
            'new'       => ['label' => 'Novos',           'color' => '#f9c74f', 'icon' => 'fas fa-plus-circle',   'value' => 0],
            'attending' => ['label' => 'Em Atendimento',  'color' => '#1976d2', 'icon' => 'fas fa-tools',         'value' => 0],
            'pending'   => ['label' => 'Pendente',        'color' => '#f57c00', 'icon' => 'fas fa-pause-circle',  'value' => 0],
            'solved'    => ['label' => 'Solucionados',    'color' => '#388e3c', 'icon' => 'fas fa-check-circle',  'value' => 0],
        ];

        foreach ($statusGroups as $key => $statuses) {
            $where = self::buildWhere($filters);
            $where['glpi_tickets.status'] = $statuses;

            $params = [
                'SELECT' => [new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`')],
                'FROM'   => 'glpi_tickets',
                'WHERE'  => $where,
            ];

            self::applyGroupJoin($params, $filters);
            self::applyTechnicianJoin($params, $filters);
            self::applyRequesterJoin($params, $filters);

            $cards[$key]['value'] = (int) ($DB->request($params)->current()['total'] ?? 0);
        }

        return [
            'type'  => 'status_cards',
            'cards' => array_values($cards),
        ];
    }

    // -------------------------------------------------------------------------
    // SLA — Tempo de Solução
    // -------------------------------------------------------------------------

    private static function getSLACompliance(array $filters): array
    {
        global $DB;

        $where   = self::buildWhere($filters);
        $where[] = ['glpi_tickets.status' => [self::STATUS_SOLVED, self::STATUS_CLOSED]];
        $where[] = new QueryExpression('`glpi_tickets`.`solvedate` IS NOT NULL');

        $params = [
            'SELECT' => [
                'glpi_tickets.priority',
                new QueryExpression(
                    "SUM(CASE WHEN `glpi_tickets`.`time_to_resolve` IS NULL
                              OR `glpi_tickets`.`solvedate` <= `glpi_tickets`.`time_to_resolve`
                         THEN 1 ELSE 0 END) AS `on_time`"
                ),
                new QueryExpression(
                    "SUM(CASE WHEN `glpi_tickets`.`time_to_resolve` IS NOT NULL
                              AND `glpi_tickets`.`solvedate` > `glpi_tickets`.`time_to_resolve`
                         THEN 1 ELSE 0 END) AS `late`"
                ),
            ],
            'FROM'    => 'glpi_tickets',
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_tickets.priority'],
            'ORDER'   => ['glpi_tickets.priority ASC'],
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        return self::buildComplianceResponse($DB->request($params), 'SLA — Tempo de Solução');
    }

    // -------------------------------------------------------------------------
    // TIT — Tempo de Atendimento (primeira resposta)
    // -------------------------------------------------------------------------

    private static function getTITCompliance(array $filters): array
    {
        global $DB;

        $where   = self::buildWhere($filters);
        $where[] = new QueryExpression('`glpi_tickets`.`takeintoaccountdate` IS NOT NULL');

        $params = [
            'SELECT' => [
                'glpi_tickets.priority',
                new QueryExpression(
                    "SUM(CASE WHEN `glpi_tickets`.`time_to_own` IS NULL
                              OR `glpi_tickets`.`takeintoaccountdate` <= `glpi_tickets`.`time_to_own`
                         THEN 1 ELSE 0 END) AS `on_time`"
                ),
                new QueryExpression(
                    "SUM(CASE WHEN `glpi_tickets`.`time_to_own` IS NOT NULL
                              AND `glpi_tickets`.`takeintoaccountdate` > `glpi_tickets`.`time_to_own`
                         THEN 1 ELSE 0 END) AS `late`"
                ),
            ],
            'FROM'    => 'glpi_tickets',
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_tickets.priority'],
            'ORDER'   => ['glpi_tickets.priority ASC'],
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        return self::buildComplianceResponse($DB->request($params), 'TIT — Tempo de Atendimento');
    }

    // -------------------------------------------------------------------------
    // Chamados por Origem
    // -------------------------------------------------------------------------

    private static function getByOrigin(array $filters): array
    {
        global $DB;

        $where = self::buildWhere($filters);

        $params = [
            'SELECT' => [
                'glpi_requesttypes.name AS origin',
                new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`'),
            ],
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_requesttypes' => [
                    'ON' => ['glpi_requesttypes' => 'id', 'glpi_tickets' => 'requesttypes_id'],
                ],
            ],
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_tickets.requesttypes_id'],
            'ORDER'   => ['total DESC'],
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        $labels = [];
        $values = [];
        $colors = [];
        $total  = 0;

        $rows = [];
        foreach ($DB->request($params) as $row) {
            $rows[] = $row;
            $total += (int) $row['total'];
        }

        foreach ($rows as $row) {
            $name     = $row['origin'] ?? __('Não definido', 'ticketdashboard');
            $count    = (int) $row['total'];
            $pct      = $total > 0 ? round($count / $total * 100, 2) : 0;
            $labels[] = $name . ' (' . $pct . '%)';
            $values[] = $count;
            $colors[] = self::seedColor($name);
        }

        if (empty($labels)) {
            return ['type' => 'empty', 'label' => __('Chamados por Origem', 'ticketdashboard')];
        }

        return [
            'type'     => 'bar',
            'labels'   => $labels,
            'datasets' => [[
                'label'           => __('Chamados', 'ticketdashboard'),
                'data'            => $values,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    // -------------------------------------------------------------------------
    // Tabela Técnico × Origem
    // -------------------------------------------------------------------------

    private static function getTechOriginMatrix(array $filters): array
    {
        global $DB;

        $where = self::buildWhere($filters);

        $params = [
            'SELECT' => [
                new QueryExpression(
                    "TRIM(CONCAT(COALESCE(`glpi_users`.`firstname`,''), ' ', COALESCE(`glpi_users`.`realname`,''))) AS `tech_name`"
                ),
                'glpi_users.id AS tech_id',
                'glpi_requesttypes.name AS origin',
                'glpi_requesttypes.id AS origin_id',
                new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`'),
            ],
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_tickets_users AS tu_m' => [
                    'ON' => [
                        'glpi_tickets' => 'id',
                        'tu_m'         => 'tickets_id',
                        ['AND' => ['tu_m.type' => 2]],
                    ],
                ],
                'glpi_users' => [
                    'ON' => ['glpi_users' => 'id', 'tu_m' => 'users_id'],
                ],
                'glpi_requesttypes' => [
                    'ON' => ['glpi_requesttypes' => 'id', 'glpi_tickets' => 'requesttypes_id'],
                ],
            ],
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_users.id', 'glpi_tickets.requesttypes_id'],
            'ORDER'   => ['tech_name ASC', 'total DESC'],
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        // Monta estrutura: techs[tech_id] = ['name' => ..., 'origins' => [origin => count]]
        $techs   = [];
        $origins = []; // origin_name => origin_id

        foreach ($DB->request($params) as $row) {
            $techId = $row['tech_id'] ?? 0;
            $name   = trim($row['tech_name']) ?: __('Não atribuído', 'ticketdashboard');
            $origin = $row['origin'] ?? __('Não definido', 'ticketdashboard');
            $count  = (int) $row['total'];

            if (!isset($techs[$techId])) {
                $techs[$techId] = ['name' => $name, 'origins' => [], 'total' => 0];
            }
            $techs[$techId]['origins'][$origin] = $count;
            $techs[$techId]['total']            += $count;
            $origins[$origin] = (int) ($row['origin_id'] ?? 0);
        }

        if (empty($techs)) {
            return ['type' => 'empty', 'label' => __('Técnico × Origem', 'ticketdashboard')];
        }

        $originList = array_keys($origins);
        sort($originList);
        $originIds = array_values(array_map(fn($o) => $origins[$o], $originList));

        // Totais por origem
        $originTotals = array_fill_keys($originList, 0);
        foreach ($techs as $t) {
            foreach ($t['origins'] as $o => $c) {
                $originTotals[$o] = ($originTotals[$o] ?? 0) + $c;
            }
        }
        $grandTotal = array_sum($originTotals);

        // Monta rows (preserva tech_id como chave)
        $rows = [];
        uasort($techs, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($techs as $techId => $t) {
            $row = ['name' => $t['name'], 'tech_id' => $techId, 'cells' => [], 'total' => $t['total']];
            foreach ($originList as $o) {
                $row['cells'][] = $t['origins'][$o] ?? 0;
            }
            $rows[] = $row;
        }

        // Linha de totais
        $totalRow = ['name' => 'Total', 'cells' => array_values($originTotals), 'total' => $grandTotal];

        return [
            'type'       => 'matrix_table',
            'drill_mode' => 'tech',
            'headers'    => $originList,
            'origin_ids' => $originIds,
            'rows'       => $rows,
            'total_row'  => $totalRow,
        ];
    }

    // -------------------------------------------------------------------------

    private static function getAuthorOriginMatrix(array $filters): array
    {
        global $DB;

        $where = self::buildWhere($filters);

        $params = [
            'SELECT' => [
                new QueryExpression(
                    "TRIM(CONCAT(COALESCE(`glpi_users`.`firstname`,''), ' ', COALESCE(`glpi_users`.`realname`,''))) AS `author_name`"
                ),
                'glpi_users.id AS author_id',
                'glpi_requesttypes.name AS origin',
                'glpi_requesttypes.id AS origin_id',
                new QueryExpression('COUNT(DISTINCT `glpi_tickets`.`id`) AS `total`'),
            ],
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_users' => [
                    'ON' => ['glpi_users' => 'id', 'glpi_tickets' => 'users_id_recipient'],
                ],
                'glpi_requesttypes' => [
                    'ON' => ['glpi_requesttypes' => 'id', 'glpi_tickets' => 'requesttypes_id'],
                ],
            ],
            'WHERE'   => $where,
            'GROUPBY' => ['glpi_tickets.users_id_recipient', 'glpi_tickets.requesttypes_id'],
            'ORDER'   => ['author_name ASC', 'total DESC'],
        ];

        self::applyGroupJoin($params, $filters);
        self::applyTechnicianJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);

        $authors = [];
        $origins = [];

        foreach ($DB->request($params) as $row) {
            $authorId = $row['author_id'] ?? 0;
            $name     = trim($row['author_name']) ?: __('Não identificado', 'ticketdashboard');
            $origin   = $row['origin'] ?? __('Não definido', 'ticketdashboard');
            $count    = (int) $row['total'];

            if (!isset($authors[$authorId])) {
                $authors[$authorId] = ['name' => $name, 'origins' => [], 'total' => 0];
            }
            $authors[$authorId]['origins'][$origin] = $count;
            $authors[$authorId]['total']            += $count;
            $origins[$origin] = (int) ($row['origin_id'] ?? 0);
        }

        if (empty($authors)) {
            return ['type' => 'empty', 'label' => __('Autor × Origem', 'ticketdashboard')];
        }

        $originList = array_keys($origins);
        sort($originList);
        $originIds = array_values(array_map(fn($o) => $origins[$o], $originList));

        $originTotals = array_fill_keys($originList, 0);
        foreach ($authors as $a) {
            foreach ($a['origins'] as $o => $c) {
                $originTotals[$o] = ($originTotals[$o] ?? 0) + $c;
            }
        }
        $grandTotal = array_sum($originTotals);

        $rows = [];
        uasort($authors, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($authors as $authorId => $a) {
            $row = ['name' => $a['name'], 'tech_id' => $authorId, 'cells' => [], 'total' => $a['total']];
            foreach ($originList as $o) {
                $row['cells'][] = $a['origins'][$o] ?? 0;
            }
            $rows[] = $row;
        }

        $totalRow = ['name' => 'Total', 'cells' => array_values($originTotals), 'total' => $grandTotal];

        return [
            'type'       => 'matrix_table',
            'drill_mode' => 'author',
            'headers'    => $originList,
            'origin_ids' => $originIds,
            'rows'       => $rows,
            'total_row'  => $totalRow,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Retorna lista de chamados para o drill-down.
     * drill_tech_id   = 0 → todos os técnicos   (matriz Técnico × Origem)
     * drill_author_id = 0 → todos os autores     (matriz Autor × Origem)
     * drill_origin_id = 0 → todas as origens
     */
    public static function getTicketDetails(array $filters): array
    {
        global $DB;

        $where          = self::buildWhere($filters);
        $drillTechId    = (int) ($filters['drill_tech_id']   ?? 0);
        $drillAuthorId  = (int) ($filters['drill_author_id'] ?? 0);
        $drillOriginId  = (int) ($filters['drill_origin_id'] ?? 0);

        if ($drillOriginId > 0) {
            $where['glpi_tickets.requesttypes_id'] = $drillOriginId;
        }

        if ($drillAuthorId > 0) {
            $where['glpi_tickets.users_id_recipient'] = $drillAuthorId;
        }

        // JOIN para técnico (nome + filtro opcional)
        $techAndCond = ['tu_d.type' => 2];
        if ($drillTechId > 0) {
            $techAndCond['tu_d.users_id'] = $drillTechId;
        }

        $params = [
            'SELECT' => [
                'glpi_tickets.id',
                'glpi_tickets.name',
                'glpi_tickets.date',
                'glpi_tickets.solvedate',
                'glpi_tickets.closedate',
                'glpi_tickets.status',
                new QueryExpression(
                    "TRIM(CONCAT(COALESCE(`tech_u`.`firstname`,''), ' ', COALESCE(`tech_u`.`realname`,''))) AS `tech_name`"
                ),
                new QueryExpression(
                    "TRIM(CONCAT(COALESCE(`author_u`.`firstname`,''), ' ', COALESCE(`author_u`.`realname`,''))) AS `author_name`"
                ),
                'glpi_requesttypes.name AS origin_name',
            ],
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_tickets_users AS tu_d' => [
                    'ON' => [
                        'glpi_tickets' => 'id',
                        'tu_d'         => 'tickets_id',
                        ['AND' => $techAndCond],
                    ],
                ],
                'glpi_users AS tech_u' => [
                    'ON' => ['tech_u' => 'id', 'tu_d' => 'users_id'],
                ],
                'glpi_users AS author_u' => [
                    'ON' => ['author_u' => 'id', 'glpi_tickets' => 'users_id_recipient'],
                ],
                'glpi_requesttypes' => [
                    'ON' => ['glpi_requesttypes' => 'id', 'glpi_tickets' => 'requesttypes_id'],
                ],
            ],
            'WHERE'  => $where,
            'ORDER'  => ['glpi_tickets.date DESC'],
        ];

        if ($drillTechId > 0) {
            $params['WHERE'][] = ['NOT' => ['tu_d.tickets_id' => null]];
        }

        self::applyGroupJoin($params, $filters);
        self::applyRequesterJoin($params, $filters);
        if ($drillTechId <= 0) {
            self::applyTechnicianJoin($params, $filters);
        }

        $statusLabels = [
            1 => __('Novo', 'ticketdashboard'),
            2 => __('Em atendimento', 'ticketdashboard'),
            3 => __('Planejado', 'ticketdashboard'),
            4 => __('Pendente', 'ticketdashboard'),
            5 => __('Resolvido', 'ticketdashboard'),
            6 => __('Fechado', 'ticketdashboard'),
        ];

        $tickets = [];
        foreach ($DB->request($params) as $row) {
            $closeDate = $row['closedate'] ?: ($row['solvedate'] ?: null);
            $tickets[] = [
                'id'        => $row['id'],
                'name'      => $row['name'],
                'tech'      => trim($row['tech_name'])   ?: __('Não atribuído', 'ticketdashboard'),
                'author'    => trim($row['author_name']) ?: __('Não identificado', 'ticketdashboard'),
                'date'      => $row['date']    ? date('d/m/Y', strtotime($row['date'])) : '—',
                'closedate' => $closeDate      ? date('d/m/Y H:i:s', strtotime($closeDate)) : '—',
                'origin'    => $row['origin_name'] ?? '—',
                'status'    => $statusLabels[$row['status']] ?? '—',
            ];
        }

        return ['tickets' => $tickets, 'total' => count($tickets)];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Constrói array WHERE para $DB->request() a partir dos filtros do usuário.
     *
     * @param  bool $includeGroup  Se true inclui filtro de grupo (para widgets que não são "por grupo")
     */
    private static function buildWhere(array $filters, bool $includeGroup = true): array
    {
        $where = [
            'glpi_tickets.is_deleted' => 0,
        ];

        // Restrição de entidade
        $entityWhere = getEntitiesRestrictCriteria('glpi_tickets');
        if (!empty($entityWhere)) {
            $where = array_merge($where, $entityWhere);
        }

        // Período
        if (!empty($filters['date_from'])) {
            $where[] = ['glpi_tickets.date' => ['>=', $filters['date_from'] . ' 00:00:00']];
        }
        if (!empty($filters['date_to'])) {
            $where[] = ['glpi_tickets.date' => ['<=', $filters['date_to'] . ' 23:59:59']];
        }

        // Tipo
        if (!empty($filters['ticket_type']) && in_array((int) $filters['ticket_type'], [1, 2], true)) {
            $where['glpi_tickets.type'] = (int) $filters['ticket_type'];
        }

        // Prioridade
        if (!empty($filters['priority']) && (int) $filters['priority'] > 0) {
            $where['glpi_tickets.priority'] = (int) $filters['priority'];
        }

        // Status
        if (!empty($filters['status']) && (int) $filters['status'] > 0) {
            $where['glpi_tickets.status'] = (int) $filters['status'];
        }

        // Autor (criador do chamado)
        if (!empty($filters['author_id']) && (int) $filters['author_id'] > 0) {
            $where['glpi_tickets.users_id_recipient'] = (int) $filters['author_id'];
        }

        // Membro do grupo: chamados onde quem abriu ou atendeu pertence ao grupo
        if (!empty($filters['member_group_id']) && (int) $filters['member_group_id'] > 0) {
            $gid = (int) $filters['member_group_id'];
            $where[] = new QueryExpression(
                "EXISTS (
                    SELECT 1
                    FROM `glpi_tickets_users` AS `tu_mg`
                    INNER JOIN `glpi_groups_users` AS `gu_mg` ON `gu_mg`.`users_id` = `tu_mg`.`users_id`
                    WHERE `tu_mg`.`tickets_id` = `glpi_tickets`.`id`
                      AND `gu_mg`.`groups_id` = {$gid}
                    UNION
                    SELECT 1
                    FROM `glpi_groups_users` AS `gu_mg2`
                    WHERE `gu_mg2`.`users_id` = `glpi_tickets`.`users_id_recipient`
                      AND `gu_mg2`.`groups_id` = {$gid}
                )"
            );
        }

        return $where;
    }

    /**
     * Adiciona INNER JOIN de requerente ao $params quando o filtro requester_id estiver ativo.
     */
    private static function applyRequesterJoin(array &$params, array $filters): void
    {
        $uid = (int) ($filters['requester_id'] ?? 0);

        if ($uid <= 0) {
            return;
        }

        $params['INNER JOIN']['glpi_tickets_users AS tu_r'] = [
            'ON' => [
                'glpi_tickets' => 'id',
                'tu_r'         => 'tickets_id',
                [
                    'AND' => [
                        'tu_r.type'     => 1,
                        'tu_r.users_id' => $uid,
                    ],
                ],
            ],
        ];
    }

    /**
     * Adiciona INNER JOIN de técnico ao $params quando o filtro users_id estiver ativo.
     * Suporta ID único (int/string) ou array de IDs (multi-seleção).
     */
    private static function applyTechnicianJoin(array &$params, array $filters): void
    {
        $raw = $filters['users_id'] ?? [];

        // Normaliza para array de inteiros válidos
        $uids = array_filter(
            array_map('intval', is_array($raw) ? $raw : [$raw]),
            fn($id) => $id > 0
        );

        if (empty($uids)) {
            return;
        }

        $params['INNER JOIN']['glpi_tickets_users AS tu_f'] = [
            'ON' => [
                'glpi_tickets' => 'id',
                'tu_f'         => 'tickets_id',
                [
                    'AND' => [
                        'tu_f.type'     => 2,
                        'tu_f.users_id' => $uids,
                    ],
                ],
            ],
        ];
    }

    /**
     * Adiciona INNER JOIN de grupo ao $params quando o filtro de grupo estiver ativo.
     */
    private static function applyGroupJoin(array &$params, array $filters): void
    {
        if (empty($filters['groups_id']) || (int) $filters['groups_id'] <= 0) {
            return;
        }

        $gid = (int) $filters['groups_id'];

        $params['INNER JOIN']['glpi_groups_tickets AS gt_f'] = [
            'ON' => [
                'glpi_tickets' => 'id',
                'gt_f'         => 'tickets_id',
                [
                    'AND' => [
                        'gt_f.type'      => 2,
                        'gt_f.groups_id' => $gid,
                    ],
                ],
            ],
        ];
    }

    /**
     * Monta resposta de conformidade (SLA ou TIT) por prioridade.
     */
    private static function buildComplianceResponse(iterable $iter, string $title): array
    {
        $priorityLabels = [
            1 => __('Muito baixa', 'ticketdashboard'),
            2 => __('Baixa', 'ticketdashboard'),
            3 => __('Média', 'ticketdashboard'),
            4 => __('Alta', 'ticketdashboard'),
            5 => __('Muito alta', 'ticketdashboard'),
            6 => __('Maior', 'ticketdashboard'),
        ];

        $labels = [];
        $onTime = [];
        $late   = [];

        foreach ($iter as $row) {
            $prio     = (int) $row['priority'];
            $labels[] = $priorityLabels[$prio] ?? "P{$prio}";
            $onTime[] = (int) $row['on_time'];
            $late[]   = (int) $row['late'];
        }

        if (empty($labels)) {
            return ['type' => 'empty', 'label' => $title];
        }

        return [
            'type'     => 'stacked_bar',
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => __('No Prazo', 'ticketdashboard'),
                    'data'            => $onTime,
                    'backgroundColor' => '#4caf50',
                ],
                [
                    'label'           => __('Fora do Prazo', 'ticketdashboard'),
                    'data'            => $late,
                    'backgroundColor' => '#f44336',
                ],
            ],
        ];
    }

    /**
     * Gera uma cor consistente baseada no nome (para grupos e técnicos).
     */
    private static function seedColor(string $seed): string
    {
        $palette = [
            '#1976d2', '#388e3c', '#f57c00', '#7b1fa2',
            '#c62828', '#00838f', '#558b2f', '#4527a0',
            '#e65100', '#283593', '#00695c', '#6a1b9a',
        ];
        return $palette[abs(crc32($seed)) % count($palette)];
    }
}

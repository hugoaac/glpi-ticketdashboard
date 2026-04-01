<?php

class PluginTicketdashboardDashboard extends CommonDBTM
{
    public static $rightname = 'ticket';

    public static function getTypeName($nb = 0): string
    {
        return __('Ticket Dashboard', 'ticketdashboard');
    }

    public static function getIcon(): string
    {
        return 'fas fa-chart-bar';
    }

    public static function getMenuContent(): array
    {
        $base = Plugin::getWebDir('ticketdashboard');

        return [
            'title'   => self::getTypeName(),
            'page'    => $base . '/front/dashboard.php',
            'icon'    => self::getIcon(),
            'options' => [
                'dashboard' => [
                    'title' => __('Painel', 'ticketdashboard'),
                    'page'  => $base . '/front/dashboard.php',
                    'icon'  => 'fas fa-tachometer-alt',
                ],
                'builder' => [
                    'title' => __('Construtor', 'ticketdashboard'),
                    'page'  => $base . '/front/builder.php',
                    'icon'  => 'fas fa-tools',
                ],
            ],
        ];
    }

    /**
     * Retorna ou cria o dashboard padrão do usuário logado.
     */
    public static function getOrCreateDefault(): self
    {
        global $DB;

        $users_id = Session::getLoginUserID();

        $row = $DB->request([
            'FROM'  => 'glpi_plugin_ticketdashboard_dashboards',
            'WHERE' => ['users_id' => $users_id, 'is_default' => 1],
            'LIMIT' => 1,
        ])->current();

        $dashboard = new self();

        if ($row) {
            $dashboard->getFromDB($row['id']);
        } else {
            $id = $dashboard->add([
                'name'          => __('Meu Dashboard', 'ticketdashboard'),
                'users_id'      => $users_id,
                'is_default'    => 1,
                'date_creation' => date('Y-m-d H:i:s'),
                'date_mod'      => date('Y-m-d H:i:s'),
            ]);
            $dashboard->getFromDB($id);

            // Adiciona widgets padrão
            PluginTicketdashboardWidget::addDefaults($id);
        }

        return $dashboard;
    }

    /**
     * Retorna todos os dashboards do usuário logado.
     */
    public static function getUserDashboards(): array
    {
        global $DB;

        $rows = [];
        $iter = $DB->request([
            'FROM'    => 'glpi_plugin_ticketdashboard_dashboards',
            'WHERE'   => ['users_id' => Session::getLoginUserID()],
            'ORDERBY' => 'name ASC',
        ]);

        foreach ($iter as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Lista de grupos para o filtro de atendimento.
     */
    public static function getGroupsForFilter(): array
    {
        global $DB;

        $groups = [];
        $iter = $DB->request([
            'SELECT'  => ['id', 'name'],
            'FROM'    => 'glpi_groups',
            'WHERE'   => ['is_assign' => 1],
            'ORDERBY' => 'name ASC',
        ]);

        foreach ($iter as $row) {
            $groups[$row['id']] = $row['name'];
        }

        return $groups;
    }

    /**
     * Lista de grupos para o filtro "Membro do Grupo".
     * Retorna todos os grupos que têm pelo menos um membro.
     */
    public static function getMemberGroupsForFilter(): array
    {
        global $DB;

        $groups = [];
        $iter = $DB->request([
            'SELECT'     => ['g.id', 'g.name'],
            'FROM'       => ['glpi_groups AS g'],
            'INNER JOIN' => [
                'glpi_groups_users AS gu' => [
                    'ON' => ['g.id', 'gu.groups_id'],
                ],
            ],
            'WHERE'   => [],
            'GROUPBY' => ['g.id'],
            'ORDERBY' => 'g.name ASC',
        ]);

        foreach ($iter as $row) {
            $groups[$row['id']] = $row['name'];
        }

        return $groups;
    }

    /**
     * Retorna o ID do primeiro grupo de atendimento do usuário logado.
     * Usado para pré-selecionar o filtro de grupo ao abrir o dashboard.
     */
    public static function getUserDefaultGroupId(): int
    {
        global $DB;

        $usersId = Session::getLoginUserID();

        $row = $DB->request([
            'SELECT'    => ['gu.groups_id'],
            'FROM'      => ['glpi_groups_users AS gu'],
            'INNER JOIN' => [
                'glpi_groups AS g' => ['ON' => ['g.id', 'gu.groups_id']],
            ],
            'WHERE' => [
                'gu.users_id' => $usersId,
                'g.is_assign' => 1,
            ],
            'LIMIT' => 1,
        ])->current();

        return $row ? (int) $row['groups_id'] : 0;
    }

    /**
     * Lista de requerentes para o filtro (usuários com tickets abertos como requerente).
     */
    public static function getRequestersForFilter(): array
    {
        global $DB;

        $requesters = [];
        $iter = $DB->request([
            'SELECT'     => ['u.id', 'u.firstname', 'u.realname', 'u.name'],
            'FROM'       => ['glpi_users AS u'],
            'INNER JOIN' => [
                'glpi_tickets_users AS tu' => [
                    'ON' => ['u.id', 'tu.users_id'],
                ],
            ],
            'WHERE'   => ['tu.type' => 1, 'u.is_active' => 1, 'u.is_deleted' => 0],
            'GROUPBY' => ['u.id'],
            'ORDERBY' => ['u.realname ASC', 'u.firstname ASC'],
        ]);

        foreach ($iter as $row) {
            $name = trim($row['firstname'] . ' ' . $row['realname']);
            if ($name === '') {
                $name = $row['name'];
            }
            $requesters[$row['id']] = $name;
        }

        return $requesters;
    }

    /**
     * Lista de autores para o filtro (usuários que criaram chamados, via users_id_recipient).
     */
    public static function getAuthorsForFilter(): array
    {
        global $DB;

        $authors = [];
        $iter = $DB->request([
            'SELECT'     => ['u.id', 'u.firstname', 'u.realname', 'u.name'],
            'FROM'       => ['glpi_users AS u'],
            'INNER JOIN' => [
                'glpi_tickets AS t' => [
                    'ON' => ['u.id', 't.users_id_recipient'],
                ],
            ],
            'WHERE'   => ['u.is_active' => 1, 'u.is_deleted' => 0, 't.is_deleted' => 0],
            'GROUPBY' => ['u.id'],
            'ORDERBY' => ['u.realname ASC', 'u.firstname ASC'],
        ]);

        foreach ($iter as $row) {
            $name = trim($row['firstname'] . ' ' . $row['realname']);
            if ($name === '') {
                $name = $row['name'];
            }
            $authors[$row['id']] = $name;
        }

        return $authors;
    }

    /**
     * Lista de técnicos para o filtro (usuários com tickets atribuídos no período).
     */
    public static function getTechniciansForFilter(): array
    {
        global $DB;

        $techs = [];
        $iter = $DB->request([
            'SELECT'   => ['u.id', 'u.firstname', 'u.realname', 'u.name'],
            'FROM'     => ['glpi_users AS u'],
            'INNER JOIN' => [
                'glpi_tickets_users AS tu' => [
                    'ON' => ['u.id', 'tu.users_id'],
                ],
            ],
            'WHERE'    => ['tu.type' => 2, 'u.is_active' => 1, 'u.is_deleted' => 0],
            'GROUPBY'  => ['u.id'],
            'ORDERBY'  => ['u.realname ASC', 'u.firstname ASC'],
        ]);

        foreach ($iter as $row) {
            $name = trim($row['firstname'] . ' ' . $row['realname']);
            if ($name === '') {
                $name = $row['name'];
            }
            $techs[$row['id']] = $name;
        }

        return $techs;
    }
}

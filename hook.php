<?php

function plugin_ticketdashboard_install(): bool
{
    global $DB;

    if (!$DB->tableExists('glpi_plugin_ticketdashboard_dashboards')) {
        $DB->doQueryOrDie(
            "CREATE TABLE `glpi_plugin_ticketdashboard_dashboards` (
                `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `name`          VARCHAR(255)  NOT NULL DEFAULT '',
                `users_id`      INT UNSIGNED  NOT NULL DEFAULT 0,
                `is_default`    TINYINT(1)    NOT NULL DEFAULT 0,
                `date_creation` TIMESTAMP     NULL DEFAULT NULL,
                `date_mod`      TIMESTAMP     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        );
    }

    if (!$DB->tableExists('glpi_plugin_ticketdashboard_widgets')) {
        $DB->doQueryOrDie(
            "CREATE TABLE `glpi_plugin_ticketdashboard_widgets` (
                `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `dashboards_id` INT UNSIGNED  NOT NULL DEFAULT 0,
                `widget_type`   VARCHAR(50)   NOT NULL DEFAULT '',
                `position`      INT           NOT NULL DEFAULT 0,
                `config`        TEXT,
                `date_mod`      TIMESTAMP     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `dashboards_id` (`dashboards_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        );
    }

    return true;
}

function plugin_ticketdashboard_uninstall(): bool
{
    global $DB;

    $DB->doQueryOrDie("DROP TABLE IF EXISTS `glpi_plugin_ticketdashboard_widgets`");
    $DB->doQueryOrDie("DROP TABLE IF EXISTS `glpi_plugin_ticketdashboard_dashboards`");

    return true;
}

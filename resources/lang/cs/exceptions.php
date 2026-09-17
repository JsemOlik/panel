<?php

return [
    'daemon_connection_failed' => 'Při komunikaci s daemonem došlo k chybě s kódem odpovědi HTTP/:code. Chyba byla zaznamenána.',
    'node' => [
        'servers_attached' => 'Node lze smazat, jen pokud k němu nejsou připojené žádné servery.',
        'daemon_off_config_updated' => 'Nastavení daemonu bylo upraveno, ale při automatické aktualizaci konfiguračního souboru na daemonu došlo k chybě. Aby se změny projevily, musíš konfigurační soubor (config.yml) upravit ručně.',
    ],
    'allocations' => [
        'server_using' => 'K této alokaci je přiřazen server. Alokaci lze smazat, jen pokud k ní není přiřazen žádný server.',
        'too_many_ports' => 'Přidání více než 1000 portů v jednom rozsahu najednou není podporováno.',
        'invalid_mapping' => 'Zadané mapování pro :port je neplatné a nelze ho zpracovat.',
        'cidr_out_of_range' => 'CIDR notace povoluje pouze masky mezi /25 a /32.',
        'port_out_of_range' => 'Porty v alokaci musí být větší než 1024 a menší nebo rovny 65535.',
    ],
    'nest' => [
        'delete_has_servers' => 'Nest s připojenými aktivními servery nelze z panelu smazat.',
        'egg' => [
            'delete_has_servers' => 'Egg s připojenými aktivními servery nelze z panelu smazat.',
            'invalid_copy_id' => 'Egg vybraný pro kopírování skriptu buď neexistuje, nebo sám skript kopíruje.',
            'must_be_child' => 'Volba „Copy Settings From“ tohoto eggu musí odkazovat na egg ze zvoleného nestu.',
            'has_children' => 'Tento egg je nadřazený jednomu nebo více jiným eggům. Před smazáním tohoto eggu smaž nejdřív je.',
        ],
        'variables' => [
            'env_not_unique' => 'Proměnná prostředí :name musí být v rámci tohoto eggu jedinečná.',
            'reserved_name' => 'Proměnná prostředí :name je chráněná a nelze ji přiřadit k proměnné.',
            'bad_validation_rule' => 'Validační pravidlo „:rule“ není pro tuto aplikaci platné.',
        ],
        'importer' => [
            'json_error' => 'Při zpracování JSON souboru došlo k chybě: :error.',
            'file_error' => 'Zadaný JSON soubor je neplatný.',
            'invalid_json_provided' => 'Zadaný JSON soubor není v rozpoznatelném formátu.',
        ],
    ],
    'subusers' => [
        'editing_self' => 'Úprava vlastního účtu spoluuživatele není povolena.',
        'user_is_owner' => 'Vlastníka serveru nelze přidat jako spoluuživatele tohoto serveru.',
        'subuser_exists' => 'Uživatel s touto e-mailovou adresou už je spoluuživatelem tohoto serveru.',
    ],
    'databases' => [
        'delete_has_databases' => 'Databázový server s připojenými aktivními databázemi nelze smazat.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'Maximální prodleva pro navazující úlohu je 15 minut.',
    ],
    'locations' => [
        'has_nodes' => 'Lokaci s připojenými aktivními nody nelze smazat.',
    ],
    'users' => [
        'node_revocation_failed' => 'Nepodařilo se zneplatnit klíče na <a href=":link">nodu #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Nebyl nalezen žádný node splňující požadavky pro automatické nasazení.',
        'no_viable_allocations' => 'Nebyla nalezena žádná alokace splňující požadavky pro automatické nasazení.',
    ],
    'api' => [
        'resource_not_found' => 'Požadovaný zdroj na tomto serveru neexistuje.',
    ],
];

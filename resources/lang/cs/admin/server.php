<?php

return [
    'exceptions' => [
        'no_new_default_allocation' => 'Pokoušíš se smazat výchozí alokaci tohoto serveru, ale není k dispozici žádná náhradní alokace.',
        'marked_as_failed' => 'Tento server je označen jako server s neúspěšnou předchozí instalací. V tomto stavu nelze stav přepnout.',
        'skipping_install_script' => 'Tento server má nastavené přeskočení instalačního skriptu eggu. Přeinstalace není dostupná, dokud toto nastavení nevypneš.',
        'bad_variable' => 'Proměnná :name neprošla validací.',
        'daemon_exception' => 'Při komunikaci s daemonem došlo k chybě s kódem odpovědi HTTP/:code. Chyba byla zaznamenána. (ID požadavku: :request_id)',
        'default_allocation_not_found' => 'Požadovaná výchozí alokace nebyla mezi alokacemi tohoto serveru nalezena.',
    ],
    'alerts' => [
        'startup_changed' => 'Nastavení spouštění serveru bylo upraveno. Pokud se změnil nest nebo egg, server se teď přeinstaluje.',
        'server_deleted' => 'Server byl úspěšně smazán ze systému.',
        'server_created' => 'Server byl úspěšně vytvořen. Dej daemonu pár minut na dokončení instalace.',
        'build_updated' => 'Parametry serveru byly upraveny. Některé změny se projeví až po restartu.',
        'suspension_toggled' => 'Stav pozastavení serveru byl změněn na :status.',
        'rebuild_on_boot' => 'Server byl označen k přestavění Docker kontejneru. Proběhne při příštím spuštění serveru.',
        'install_toggled' => 'Stav instalace serveru byl přepnut.',
        'server_reinstalled' => 'Server byl zařazen do fronty k přeinstalaci, která právě začíná.',
        'details_updated' => 'Údaje serveru byly úspěšně upraveny.',
        'docker_image_updated' => 'Výchozí Docker image serveru byl úspěšně změněn. Změna se projeví po restartu.',
        'node_required' => 'Před přidáním serveru musíš mít nastavený alespoň jeden node.',
        'transfer_nodes_required' => 'Pro přesun serverů musíš mít nastavené alespoň dva nody.',
        'transfer_started' => 'Přesun serveru byl zahájen.',
        'transfer_not_viable' => 'Vybraný node nemá dost volného místa na disku nebo paměti pro tento server.',
    ],
];

<?php

return [
    'validation' => [
        'fqdn_not_resolvable' => 'Zadané FQDN nebo IP adresa se nepřekládá na platnou IP adresu.',
        'fqdn_required_for_ssl' => 'Pro použití SSL na tomto nodu je potřeba plně kvalifikované doménové jméno, které se překládá na veřejnou IP adresu.',
    ],
    'notices' => [
        'allocations_added' => 'Alokace byly úspěšně přidány k tomuto nodu.',
        'node_deleted' => 'Node byl úspěšně odebrán z panelu.',
        'location_required' => 'Před přidáním nodu musíš mít nastavenou alespoň jednu lokaci.',
        'node_created' => 'Nový node byl úspěšně vytvořen. Daemon na tomto stroji můžeš automaticky nastavit na záložce „Configuration“. Před přidáním serverů musíš nejdřív přidat alespoň jednu IP adresu a port.',
        'node_updated' => 'Údaje nodu byly upraveny. Pokud se změnilo nastavení daemonu, restartuj ho, aby se změny projevily.',
        'unallocated_deleted' => 'Smazány všechny nepřiřazené porty pro <code>:ip</code>.',
    ],
];

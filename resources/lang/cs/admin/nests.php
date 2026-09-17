<?php

return [
    'notices' => [
        'created' => 'Nový nest :name byl úspěšně vytvořen.',
        'deleted' => 'Nest byl úspěšně smazán z panelu.',
        'updated' => 'Nastavení nestu bylo úspěšně upraveno.',
    ],
    'eggs' => [
        'notices' => [
            'imported' => 'Egg a jeho proměnné byly úspěšně naimportovány.',
            'updated_via_import' => 'Egg byl aktualizován z nahraného souboru.',
            'deleted' => 'Egg byl úspěšně smazán z panelu.',
            'updated' => 'Nastavení eggu bylo úspěšně upraveno.',
            'script_updated' => 'Instalační skript eggu byl upraven a spustí se při každé instalaci serveru.',
            'egg_created' => 'Nový egg byl úspěšně vytvořen. Aby se projevil, restartuj všechny běžící daemony.',
        ],
    ],
    'variables' => [
        'notices' => [
            'variable_deleted' => 'Proměnná „:variable“ byla smazána a po přestavění už nebude serverům dostupná.',
            'variable_updated' => 'Proměnná „:variable“ byla upravena. Aby se změny projevily, přestav servery, které ji používají.',
            'variable_created' => 'Nová proměnná byla úspěšně vytvořena a přiřazena k tomuto eggu.',
        ],
    ],
];

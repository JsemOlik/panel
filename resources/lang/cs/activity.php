<?php

/**
 * Contains all of the translation strings for different activity log
 * events. These should be keyed by the value in front of the colon (:)
 * in the event name. If there is no colon present, they should live at
 * the top level.
 */
return [
    'auth' => [
        'fail' => 'Neúspěšné přihlášení',
        'success' => 'Přihlášení',
        'password-reset' => 'Obnovení hesla',
        'reset-password' => 'Žádost o obnovení hesla',
        'checkpoint' => 'Vyžádáno dvoufázové ověření',
        'recovery-token' => 'Použit záložní kód dvoufázového ověření',
        'token' => 'Dokončeno dvoufázové ověření',
        'ip-blocked' => 'Zablokován požadavek z nepovolené IP adresy pro :identifier',
        'oauth' => [
            'fail' => 'Neúspěšné přihlášení přes :provider',
        ],
        'sftp' => [
            'fail' => 'Neúspěšné přihlášení přes SFTP',
        ],
    ],
    'user' => [
        'user' => [
            'create' => 'Vytvořen nový uživatel :email',
        ],
        'account' => [
            'email-changed' => 'Změněn e-mail z :old na :new',
            'password-changed' => 'Změněno heslo',
        ],
        'api-key' => [
            'create' => 'Vytvořen nový API klíč :identifier',
            'delete' => 'Smazán API klíč :identifier',
        ],
        'oauth' => [
            'link' => 'Propojen účet :provider',
            'unlink' => 'Odpojen účet :provider',
        ],
        'ssh-key' => [
            'create' => 'Přidán SSH klíč :fingerprint k účtu',
            'delete' => 'Odebrán SSH klíč :fingerprint z účtu',
        ],
        'two-factor' => [
            'create' => 'Zapnuto dvoufázové ověření',
            'delete' => 'Vypnuto dvoufázové ověření',
        ],
    ],
    'server' => [
        'reinstall' => 'Přeinstalován server',
        'console' => [
            'command' => 'Spuštěn příkaz „:command“ na serveru',
        ],
        'power' => [
            'start' => 'Spuštěn server',
            'stop' => 'Zastaven server',
            'restart' => 'Restartován server',
            'kill' => 'Násilně ukončen proces serveru',
        ],
        'backup' => [
            'download' => 'Stažena záloha :name',
            'delete' => 'Smazána záloha :name',
            'restore' => 'Obnovena záloha :name (smazané soubory: :truncate)',
            'restore-complete' => 'Dokončeno obnovení zálohy :name',
            'restore-failed' => 'Nepodařilo se dokončit obnovení zálohy :name',
            'start' => 'Spuštěna nová záloha :name',
            'complete' => 'Záloha :name označena jako dokončená',
            'fail' => 'Záloha :name označena jako neúspěšná',
            'lock' => 'Zamčena záloha :name',
            'unlock' => 'Odemčena záloha :name',
        ],
        'database' => [
            'create' => 'Vytvořena nová databáze :name',
            'rotate-password' => 'Vygenerováno nové heslo pro databázi :name',
            'delete' => 'Smazána databáze :name',
        ],
        'file' => [
            'compress_one' => 'Zkomprimován :directory:files.0',
            'compress_few' => 'Zkomprimovány :count soubory v :directory',
            'compress_many' => 'Zkomprimováno :count souborů v :directory',
            'compress_other' => 'Zkomprimováno :count souborů v :directory',
            'read' => 'Zobrazen obsah :file',
            'copy' => 'Vytvořena kopie :file',
            'create-directory' => 'Vytvořena složka :directory:name',
            'decompress' => 'Rozbaleno :files v :directory',
            'delete_one' => 'Smazán :directory:files.0',
            'delete_few' => 'Smazány :count soubory v :directory',
            'delete_many' => 'Smazáno :count souborů v :directory',
            'delete_other' => 'Smazáno :count souborů v :directory',
            'download' => 'Stažen :file',
            'pull' => 'Stažen vzdálený soubor z :url do :directory',
            'rename_one' => 'Přejmenován :directory:files.0.from na :directory:files.0.to',
            'rename_few' => 'Přejmenovány :count soubory v :directory',
            'rename_many' => 'Přejmenováno :count souborů v :directory',
            'rename_other' => 'Přejmenováno :count souborů v :directory',
            'write' => 'Zapsán nový obsah do :file',
            'upload' => 'Zahájeno nahrávání souboru',
            'uploaded' => 'Nahrán :directory:file',
        ],
        'sftp' => [
            'denied' => 'Zablokován přístup přes SFTP kvůli oprávněním',
            'create_one' => 'Vytvořen :files.0',
            'create_few' => 'Vytvořeny :count nové soubory',
            'create_many' => 'Vytvořeno :count nových souborů',
            'create_other' => 'Vytvořeno :count nových souborů',
            'write_one' => 'Upraven obsah :files.0',
            'write_few' => 'Upraven obsah :count souborů',
            'write_many' => 'Upraven obsah :count souborů',
            'write_other' => 'Upraven obsah :count souborů',
            'delete_one' => 'Smazán :files.0',
            'delete_few' => 'Smazány :count soubory',
            'delete_many' => 'Smazáno :count souborů',
            'delete_other' => 'Smazáno :count souborů',
            'create-directory_one' => 'Vytvořena složka :files.0',
            'create-directory_few' => 'Vytvořeny :count složky',
            'create-directory_many' => 'Vytvořeno :count složek',
            'create-directory_other' => 'Vytvořeno :count složek',
            'rename_one' => 'Přejmenován :files.0.from na :files.0.to',
            'rename_few' => 'Přejmenovány nebo přesunuty :count soubory',
            'rename_many' => 'Přejmenováno nebo přesunuto :count souborů',
            'rename_other' => 'Přejmenováno nebo přesunuto :count souborů',
        ],
        'allocation' => [
            'create' => 'Přidána alokace :allocation k serveru',
            'notes' => 'Upraveny poznámky k alokaci :allocation z „:old“ na „:new“',
            'primary' => 'Alokace :allocation nastavena jako hlavní alokace serveru',
            'delete' => 'Smazána alokace :allocation',
        ],
        'schedule' => [
            'create' => 'Vytvořen plán :name',
            'update' => 'Upraven plán :name',
            'execute' => 'Ručně spuštěn plán :name',
            'delete' => 'Smazán plán :name',
        ],
        'task' => [
            'create' => 'Vytvořena nová úloha „:action“ pro plán :name',
            'update' => 'Upravena úloha „:action“ pro plán :name',
            'delete' => 'Smazána úloha pro plán :name',
        ],
        'settings' => [
            'rename' => 'Server přejmenován z :old na :new',
            'description' => 'Změněn popis serveru z :old na :new',
        ],
        'startup' => [
            'edit' => 'Změněna proměnná :variable z „:old“ na „:new“',
            'image' => 'Změněn Docker image serveru z :old na :new',
        ],
        'subuser' => [
            'create' => 'Přidán :email jako spoluuživatel',
            'update' => 'Upravena oprávnění spoluuživatele :email',
            'delete' => 'Odebrán spoluuživatel :email',
        ],
    ],
];

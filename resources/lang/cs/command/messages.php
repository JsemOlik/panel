<?php

return [
    'location' => [
        'no_location_found' => 'Nebyl nalezen žádný záznam se zadaným krátkým kódem.',
        'ask_short' => 'Krátký kód lokace',
        'ask_long' => 'Popis lokace',
        'created' => 'Nová lokace (:name) byla úspěšně vytvořena s ID :id.',
        'deleted' => 'Lokace byla úspěšně smazána.',
    ],
    'user' => [
        'search_users' => 'Zadej uživatelské jméno, ID uživatele nebo e-mailovou adresu',
        'select_search_user' => 'ID uživatele ke smazání (zadej \'0\' pro nové hledání)',
        'deleted' => 'Uživatel byl úspěšně smazán z panelu.',
        'confirm_delete' => 'Opravdu chceš tohoto uživatele smazat z panelu?',
        'no_users_found' => 'Pro zadaný výraz nebyl nalezen žádný uživatel.',
        'multiple_found' => 'Pro zadaného uživatele bylo nalezeno více účtů, kvůli přepínači --no-interaction nelze uživatele smazat.',
        'ask_admin' => 'Je tento uživatel administrátor?',
        'ask_email' => 'E-mailová adresa',
        'ask_username' => 'Uživatelské jméno',
        'ask_name_first' => 'Jméno',
        'ask_name_last' => 'Příjmení',
        'ask_password' => 'Heslo',
        'ask_password_tip' => 'Pokud chceš vytvořit účet s náhodným heslem zaslaným uživateli e-mailem, spusť příkaz znovu (CTRL+C) s přepínačem `--no-password`.',
        'ask_password_help' => 'Heslo musí mít alespoň 8 znaků a obsahovat alespoň jedno velké písmeno a číslici.',
        '2fa_help_text' => [
            'Tento příkaz vypne dvoufázové ověření na účtu uživatele, pokud je zapnuté. Používej ho jen k obnovení přístupu, když se uživatel nemůže dostat do svého účtu.',
            'Pokud tohle nechceš, ukonči proces pomocí CTRL+C.',
        ],
        '2fa_disabled' => 'Dvoufázové ověření bylo pro :email vypnuto.',
    ],
    'schedule' => [
        'output_line' => 'Odesílám úlohu pro první krok plánu `:schedule` (:hash).',
    ],
    'maintenance' => [
        'deleting_service_backup' => 'Mažu záložní soubor služby :file.',
    ],
    'server' => [
        'rebuild_failed' => 'Požadavek na přestavění serveru „:name“ (#:id) na nodu „:node“ selhal s chybou: :message',
        'reinstall' => [
            'failed' => 'Požadavek na přeinstalaci serveru „:name“ (#:id) na nodu „:node“ selhal s chybou: :message',
            'confirm' => 'Chystáš se přeinstalovat skupinu serverů. Chceš pokračovat?',
        ],
        'power' => [
            'confirm' => 'Chystáš se provést akci :action na :count serverech. Chceš pokračovat?',
            'action_failed' => 'Požadavek na akci napájení pro „:name“ (#:id) na nodu „:node“ selhal s chybou: :message',
        ],
    ],
    'environment' => [
        'mail' => [
            'ask_smtp_host' => 'SMTP server (např. smtp.gmail.com)',
            'ask_smtp_port' => 'SMTP port',
            'ask_smtp_username' => 'SMTP uživatelské jméno',
            'ask_smtp_password' => 'SMTP heslo',
            'ask_mailgun_domain' => 'Mailgun doména',
            'ask_mailgun_endpoint' => 'Mailgun endpoint',
            'ask_mailgun_secret' => 'Mailgun tajný klíč',
            'ask_mandrill_secret' => 'Mandrill tajný klíč',
            'ask_postmark_username' => 'Postmark API klíč',
            'ask_driver' => 'Který ovladač použít pro odesílání e-mailů?',
            'ask_mail_from' => 'E-mailová adresa, ze které se budou e-maily odesílat',
            'ask_mail_name' => 'Jméno odesílatele e-mailů',
            'ask_encryption' => 'Způsob šifrování',
        ],
    ],
];

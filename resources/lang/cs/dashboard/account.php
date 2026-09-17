<?php

return [
    'email' => [
        'title' => 'Změnit e-mail',
        'updated' => 'Tvá e-mailová adresa byla změněna.',
    ],
    'password' => [
        'title' => 'Změnit heslo',
        'requirements' => 'Nové heslo musí mít alespoň 8 znaků.',
        'updated' => 'Tvé heslo bylo změněno.',
    ],
    'two_factor' => [
        'button' => 'Nastavit dvoufázové ověření',
        'disabled' => 'Dvoufázové ověření bylo na tvém účtu vypnuto. Při přihlášení už nebudeš muset zadávat kód.',
        'enabled' => 'Dvoufázové ověření bylo na tvém účtu zapnuto! Při přihlášení budeš od teď zadávat kód vygenerovaný tvým zařízením.',
        'invalid' => 'Zadaný kód je neplatný.',
        'setup' => [
            'title' => 'Nastavit dvoufázové ověření',
            'help' => 'Nemůžeš naskenovat kód? Zadej do aplikace tento kód:',
            'field' => 'Zadej kód',
        ],
        'disable' => [
            'title' => 'Vypnout dvoufázové ověření',
            'field' => 'Zadej kód',
        ],
    ],
];

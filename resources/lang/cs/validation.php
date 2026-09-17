<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Pole :attribute musí být přijato.',
    'active_url' => 'Pole :attribute není platná URL adresa.',
    'after' => 'Pole :attribute musí být datum po :date.',
    'after_or_equal' => 'Pole :attribute musí být datum :date nebo pozdější.',
    'alpha' => 'Pole :attribute smí obsahovat pouze písmena.',
    'alpha_dash' => 'Pole :attribute smí obsahovat pouze písmena, číslice a pomlčky.',
    'alpha_num' => 'Pole :attribute smí obsahovat pouze písmena a číslice.',
    'array' => 'Pole :attribute musí být seznam.',
    'before' => 'Pole :attribute musí být datum před :date.',
    'before_or_equal' => 'Pole :attribute musí být datum :date nebo dřívější.',
    'between' => [
        'numeric' => 'Pole :attribute musí být mezi :min a :max.',
        'file' => 'Pole :attribute musí mít velikost mezi :min a :max kilobajty.',
        'string' => 'Pole :attribute musí mít délku mezi :min a :max znaky.',
        'array' => 'Pole :attribute musí obsahovat :min až :max položek.',
    ],
    'boolean' => 'Pole :attribute musí být ano nebo ne.',
    'confirmed' => 'Potvrzení pole :attribute se neshoduje.',
    'date' => 'Pole :attribute není platné datum.',
    'date_format' => 'Pole :attribute neodpovídá formátu :format.',
    'different' => 'Pole :attribute a :other se musí lišit.',
    'digits' => 'Pole :attribute musí mít :digits číslic.',
    'digits_between' => 'Pole :attribute musí mít :min až :max číslic.',
    'dimensions' => 'Obrázek v poli :attribute má neplatné rozměry.',
    'distinct' => 'Pole :attribute obsahuje duplicitní hodnotu.',
    'email' => 'Pole :attribute musí být platná e-mailová adresa.',
    'exists' => 'Zvolená hodnota pole :attribute je neplatná.',
    'file' => 'Pole :attribute musí být soubor.',
    'filled' => 'Pole :attribute je povinné.',
    'image' => 'Pole :attribute musí být obrázek.',
    'in' => 'Zvolená hodnota pole :attribute je neplatná.',
    'in_array' => 'Pole :attribute neexistuje v :other.',
    'integer' => 'Pole :attribute musí být celé číslo.',
    'ip' => 'Pole :attribute musí být platná IP adresa.',
    'json' => 'Pole :attribute musí být platný JSON řetězec.',
    'max' => [
        'numeric' => 'Pole :attribute nesmí být větší než :max.',
        'file' => 'Pole :attribute nesmí být větší než :max kilobajtů.',
        'string' => 'Pole :attribute nesmí být delší než :max znaků.',
        'array' => 'Pole :attribute nesmí obsahovat více než :max položek.',
    ],
    'mimes' => 'Pole :attribute musí být soubor typu: :values.',
    'mimetypes' => 'Pole :attribute musí být soubor typu: :values.',
    'min' => [
        'numeric' => 'Pole :attribute musí být alespoň :min.',
        'file' => 'Pole :attribute musí mít alespoň :min kilobajtů.',
        'string' => 'Pole :attribute musí mít alespoň :min znaků.',
        'array' => 'Pole :attribute musí obsahovat alespoň :min položek.',
    ],
    'not_in' => 'Zvolená hodnota pole :attribute je neplatná.',
    'numeric' => 'Pole :attribute musí být číslo.',
    'present' => 'Pole :attribute musí být přítomno.',
    'regex' => 'Pole :attribute má neplatný formát.',
    'required' => 'Pole :attribute je povinné.',
    'required_if' => 'Pole :attribute je povinné, pokud :other je :value.',
    'required_unless' => 'Pole :attribute je povinné, pokud :other není v :values.',
    'required_with' => 'Pole :attribute je povinné, pokud je vyplněno :values.',
    'required_with_all' => 'Pole :attribute je povinné, pokud je vyplněno :values.',
    'required_without' => 'Pole :attribute je povinné, pokud není vyplněno :values.',
    'required_without_all' => 'Pole :attribute je povinné, pokud není vyplněno žádné z :values.',
    'same' => 'Pole :attribute a :other se musí shodovat.',
    'size' => [
        'numeric' => 'Pole :attribute musí být :size.',
        'file' => 'Pole :attribute musí mít :size kilobajtů.',
        'string' => 'Pole :attribute musí mít :size znaků.',
        'array' => 'Pole :attribute musí obsahovat :size položek.',
    ],
    'string' => 'Pole :attribute musí být text.',
    'timezone' => 'Pole :attribute musí být platné časové pásmo.',
    'unique' => 'Hodnota pole :attribute je již použita.',
    'uploaded' => 'Nahrání pole :attribute se nezdařilo.',
    'url' => 'Pole :attribute má neplatný formát.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [],

    // Internal validation logic for Pterodactyl
    'internal' => [
        'variable_value' => 'proměnná :env',
        'invalid_password' => 'Zadané heslo pro tento účet není platné.',
    ],
];

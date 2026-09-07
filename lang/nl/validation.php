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

    'accepted' => ':Attribute moet geaccepteerd worden.',
    'accepted_if' => ':Attribute moet geaccepteerd worden wanneer :other :value is.',
    'active_url' => ':Attribute is geen geldige URL.',
    'after' => ':Attribute moet een datum na :date zijn.',
    'after_or_equal' => ':Attribute moet een datum op of na :date zijn.',
    'alpha' => ':Attribute mag alleen letters bevatten.',
    'alpha_dash' => ':Attribute mag alleen letters, cijfers, streepjes en underscores bevatten.',
    'alpha_num' => ':Attribute mag alleen letters en cijfers bevatten.',
    'any_of' => ':Attribute is ongeldig.',
    'array' => ':Attribute moet een lijst zijn.',
    'ascii' => ':Attribute mag alleen alfanumerieke tekens en symbolen van één byte bevatten.',
    'before' => ':Attribute moet een datum vóór :date zijn.',
    'before_or_equal' => ':Attribute moet een datum op of vóór :date zijn.',
    'between' => [
        'array' => ':Attribute moet tussen :min en :max items bevatten.',
        'file' => ':Attribute moet tussen :min en :max kilobytes zijn.',
        'numeric' => ':Attribute moet tussen :min en :max zijn.',
        'string' => ':Attribute moet tussen :min en :max tekens zijn.',
    ],
    'boolean' => ':Attribute moet ja of nee zijn.',
    'can' => ':Attribute bevat een waarde waarvoor je geen rechten hebt.',
    'confirmed' => 'De bevestiging van :attribute komt niet overeen.',
    'contains' => ':Attribute mist een vereiste waarde.',
    'current_password' => 'Het wachtwoord is onjuist.',
    'date' => ':Attribute is geen geldige datum.',
    'date_equals' => ':Attribute moet een datum gelijk aan :date zijn.',
    'date_format' => ':Attribute komt niet overeen met het formaat :format.',
    'decimal' => ':Attribute moet :decimal decimalen hebben.',
    'declined' => ':Attribute moet geweigerd worden.',
    'declined_if' => ':Attribute moet geweigerd worden wanneer :other :value is.',
    'different' => ':Attribute en :other moeten verschillend zijn.',
    'digits' => ':Attribute moet :digits cijfers zijn.',
    'digits_between' => ':Attribute moet tussen :min en :max cijfers zijn.',
    'dimensions' => ':Attribute heeft ongeldige afbeeldingsafmetingen.',
    'distinct' => ':Attribute heeft een dubbele waarde.',
    'doesnt_contain' => ':Attribute mag geen van de volgende waarden bevatten: :values.',
    'doesnt_end_with' => ':Attribute mag niet eindigen op: :values.',
    'doesnt_start_with' => ':Attribute mag niet beginnen met: :values.',
    'email' => ':Attribute moet een geldig e-mailadres zijn.',
    'ends_with' => ':Attribute moet eindigen op: :values.',
    'enum' => 'De geselecteerde :attribute is ongeldig.',
    'exists' => 'De geselecteerde :attribute is ongeldig.',
    'extensions' => ':Attribute moet een van de volgende extensies hebben: :values.',
    'file' => ':Attribute moet een bestand zijn.',
    'filled' => ':Attribute moet een waarde bevatten.',
    'gt' => [
        'array' => ':Attribute moet meer dan :value items bevatten.',
        'file' => ':Attribute moet groter zijn dan :value kilobytes.',
        'numeric' => ':Attribute moet groter zijn dan :value.',
        'string' => ':Attribute moet meer dan :value tekens bevatten.',
    ],
    'gte' => [
        'array' => ':Attribute moet :value of meer items bevatten.',
        'file' => ':Attribute moet :value kilobytes of groter zijn.',
        'numeric' => ':Attribute moet :value of groter zijn.',
        'string' => ':Attribute moet :value tekens of meer bevatten.',
    ],
    'hex_color' => ':Attribute moet een geldige hexadecimale kleur zijn.',
    'image' => ':Attribute moet een afbeelding zijn.',
    'in' => 'De geselecteerde :attribute is ongeldig.',
    'in_array' => ':Attribute moet voorkomen in :other.',
    'in_array_keys' => ':Attribute moet minimaal een van de volgende sleutels bevatten: :values.',
    'integer' => ':Attribute moet een geheel getal zijn.',
    'ip' => ':Attribute moet een geldig IP-adres zijn.',
    'ipv4' => ':Attribute moet een geldig IPv4-adres zijn.',
    'ipv6' => ':Attribute moet een geldig IPv6-adres zijn.',
    'json' => ':Attribute moet een geldige JSON-string zijn.',
    'list' => ':Attribute moet een lijst zijn.',
    'lowercase' => ':Attribute moet uit kleine letters bestaan.',
    'lt' => [
        'array' => ':Attribute moet minder dan :value items bevatten.',
        'file' => ':Attribute moet kleiner zijn dan :value kilobytes.',
        'numeric' => ':Attribute moet kleiner zijn dan :value.',
        'string' => ':Attribute moet minder dan :value tekens bevatten.',
    ],
    'lte' => [
        'array' => ':Attribute mag niet meer dan :value items bevatten.',
        'file' => ':Attribute moet :value kilobytes of kleiner zijn.',
        'numeric' => ':Attribute moet :value of kleiner zijn.',
        'string' => ':Attribute moet :value tekens of minder bevatten.',
    ],
    'mac_address' => ':Attribute moet een geldig MAC-adres zijn.',
    'max' => [
        'array' => ':Attribute mag niet meer dan :max items bevatten.',
        'file' => ':Attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':Attribute mag niet groter zijn dan :max.',
        'string' => ':Attribute mag niet meer dan :max tekens bevatten.',
    ],
    'max_digits' => ':Attribute mag niet meer dan :max cijfers bevatten.',
    'mimes' => ':Attribute moet een bestand zijn van het type: :values.',
    'mimetypes' => ':Attribute moet een bestand zijn van het type: :values.',
    'min' => [
        'array' => ':Attribute moet minimaal :min items bevatten.',
        'file' => ':Attribute moet minimaal :min kilobytes zijn.',
        'numeric' => ':Attribute moet minimaal :min zijn.',
        'string' => ':Attribute moet minimaal :min tekens bevatten.',
    ],
    'min_digits' => ':Attribute moet minimaal :min cijfers bevatten.',
    'missing' => ':Attribute moet ontbreken.',
    'missing_if' => ':Attribute moet ontbreken wanneer :other :value is.',
    'missing_unless' => ':Attribute moet ontbreken tenzij :other :value is.',
    'missing_with' => ':Attribute moet ontbreken wanneer :values aanwezig is.',
    'missing_with_all' => ':Attribute moet ontbreken wanneer :values aanwezig zijn.',
    'multiple_of' => ':Attribute moet een veelvoud van :value zijn.',
    'not_in' => 'De geselecteerde :attribute is ongeldig.',
    'not_regex' => 'Het formaat van :attribute is ongeldig.',
    'numeric' => ':Attribute moet een getal zijn.',
    'password' => [
        'letters' => ':Attribute moet minimaal één letter bevatten.',
        'mixed' => ':Attribute moet minimaal één kleine letter en één hoofdletter bevatten.',
        'numbers' => ':Attribute moet minimaal één cijfer bevatten.',
        'symbols' => ':Attribute moet minimaal één symbool bevatten.',
        'uncompromised' => 'Het opgegeven :attribute komt voor in een datalek. Kies een ander :attribute.',
    ],
    'present' => ':Attribute moet aanwezig zijn.',
    'present_if' => ':Attribute moet aanwezig zijn wanneer :other :value is.',
    'present_unless' => ':Attribute moet aanwezig zijn tenzij :other :value is.',
    'present_with' => ':Attribute moet aanwezig zijn wanneer :values aanwezig is.',
    'present_with_all' => ':Attribute moet aanwezig zijn wanneer :values aanwezig zijn.',
    'prohibited' => ':Attribute is niet toegestaan.',
    'prohibited_if' => ':Attribute is niet toegestaan wanneer :other :value is.',
    'prohibited_if_accepted' => ':Attribute is niet toegestaan wanneer :other is geaccepteerd.',
    'prohibited_if_declined' => ':Attribute is niet toegestaan wanneer :other is geweigerd.',
    'prohibited_unless' => ':Attribute is niet toegestaan tenzij :other in :values voorkomt.',
    'prohibits' => ':Attribute verbiedt de aanwezigheid van :other.',
    'regex' => 'Het formaat van :attribute is ongeldig.',
    'required' => ':Attribute is verplicht.',
    'required_array_keys' => ':Attribute moet de volgende sleutels bevatten: :values.',
    'required_if' => ':Attribute is verplicht wanneer :other :value is.',
    'required_if_accepted' => ':Attribute is verplicht wanneer :other is geaccepteerd.',
    'required_if_declined' => ':Attribute is verplicht wanneer :other is geweigerd.',
    'required_unless' => ':Attribute is verplicht tenzij :other in :values voorkomt.',
    'required_with' => ':Attribute is verplicht wanneer :values aanwezig is.',
    'required_with_all' => ':Attribute is verplicht wanneer :values aanwezig zijn.',
    'required_without' => ':Attribute is verplicht wanneer :values niet aanwezig is.',
    'required_without_all' => ':Attribute is verplicht wanneer geen van :values aanwezig is.',
    'same' => ':Attribute en :other moeten overeenkomen.',
    'size' => [
        'array' => ':Attribute moet :size items bevatten.',
        'file' => ':Attribute moet :size kilobytes zijn.',
        'numeric' => ':Attribute moet :size zijn.',
        'string' => ':Attribute moet :size tekens zijn.',
    ],
    'starts_with' => ':Attribute moet beginnen met: :values.',
    'string' => ':Attribute moet een tekst zijn.',
    'timezone' => ':Attribute moet een geldige tijdzone zijn.',
    'unique' => ':Attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',
    'uppercase' => ':Attribute moet uit hoofdletters bestaan.',
    'url' => ':Attribute moet een geldige URL zijn.',
    'ulid' => ':Attribute moet een geldige ULID zijn.',
    'uuid' => ':Attribute moet een geldige UUID zijn.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'naam',
        'email' => 'e-mailadres',
        'password' => 'wachtwoord',
        'current_password' => 'huidige wachtwoord',
        'code' => 'code',
    ],

];

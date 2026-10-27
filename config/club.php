<?php

return [
    /*
    | Superusuario de la plataforma, creado por el seeder en la instalación inicial.
    */
    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    /*
    | Entidad principal que se crea al instalar (luego se agregan más desde el sistema).
    | Tipos: club, gimnasio, mixto (club con gimnasio).
    */
    'default_organization' => [
        'name' => env('DEFAULT_ORGANIZATION_NAME', 'Club Social y Deportivo'),
        'type' => env('DEFAULT_ORGANIZATION_TYPE', 'club'),
    ],

    /*
    | Competencias: decimales fijos con que se guardan, calculan y comparan los puntajes
    | (las columnas son numeric(8,3); con la misma escala los empates se detectan bien).
    */
    'competition_score_scale' => 3,
];

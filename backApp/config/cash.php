<?php
// config/cash.php

return [
    'categories' => [
        'income' => [
            'donation'     => 'Don',
            'subvention'   => 'Subvention',
            'other_income' => 'Autre recette',
        ],
        'expense' => [
            'salary'       => 'Salaire',
            'supplies'     => 'Fournitures',
            'rent'         => 'Loyer',
            'utilities'    => 'Eau / Électricité / Internet',
            'maintenance'  => 'Entretien',
            'marketing'    => 'Publicité / Marketing',
            'transport'    => 'Transport',
            'other_expense'=> 'Autre dépense',
        ],
    ],

    'attachments' => [
        'disk'      => 'private',
        'max_size'  => 5 * 1024 * 1024, // 5 Mo
        'mimes'     => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
];
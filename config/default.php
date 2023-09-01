<?php

    /**
     * Sample site configuration file for UserFrosting.  You should definitely set these values!
     *
     */
    return [
        'address_book' => [
            'admin' => [
                'name'  => 'UserFrosting'
            ]
        ],
        'session' => [
            'name'          => 'uf-demo'
        ],
        'site' => [
            'title'     =>      'UserFrosting Demo',
            'analytics' => [
                'google' => [
                    'code' => 'UA-37689257-2'
                ]
            ],
            'author'    =>      'Alex Weissman',
            'locales' => [
                'default' => 'en_US'
            ],
            // URLs
            'uri' => [
                'public' => 'https://demo.userfrosting.com',
                'author' => 'https://alexanderweissman.com'
            ]
        ]
    ];
    
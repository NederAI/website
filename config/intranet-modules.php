<?php
return [
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => 'home',
        'description' => 'Snel overzicht van voortgang, acties en controles.',
        'category' => 'overview',
        'roles' => ['intranet.member'],
        'widgets' => [
            [
                'type' => 'info',
                'title' => 'Welkom terug',
                'body' => "Gebruik de snelle acties om je dag te starten. Alles is erop gericht om stappen te automatiseren in plaats van te administreren.",
                'actions' => [
                    [
                        'id' => 'refresh-digest',
                        'label' => 'Ververs overzicht',
                        'style' => 'secondary',
                        'automation' => 'dashboard.refresh_digest',
                    ],
                ],
            ],
            [
                'type' => 'list',
                'title' => 'Snelle taken',
                'items' => [
                    'Controleer de besluitagenda',
                    'Werk cirkelrollen bij',
                    'Plan een evaluatie',
                ],
            ],
        ],
        'automations' => [
            [
                'id' => 'dashboard.refresh_digest',
                'label' => 'Ververs het overzicht',
                'description' => 'Plant een achtergrondtaak om de laatste cijfers te verzamelen.',
                'fields' => [
                    [
                        'id' => 'scope',
                        'label' => 'Bereik',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => 'today', 'label' => 'Alleen vandaag'],
                            ['value' => 'week', 'label' => 'Laatste zeven dagen'],
                            ['value' => 'month', 'label' => 'Laatste maand'],
                        ],
                        'default' => 'today',
                    ],
                    [
                        'id' => 'notify',
                        'label' => 'Stuur melding wanneer klaar',
                        'type' => 'boolean',
                        'default' => true,
                    ],
                ],
                'success_message' => 'De verversing is ingepland.',
            ],
        ],
    ],
    [
        'id' => 'profile',
        'label' => 'Profiel',
        'icon' => 'user',
        'description' => 'Beheer je persoonlijke instellingen en meldingen.',
        'category' => 'selfservice',
        'roles' => ['intranet.member'],
        'widgets' => [
            [
                'type' => 'form',
                'id' => 'profile.nickname',
                'title' => 'Weergavenaam',
                'support' => 'Deze naam tonen we in dashboards en notities.',
                'fields' => [
                    [
                        'id' => 'nickname',
                        'label' => 'Weergavenaam',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'Bijvoorbeeld: Alex',
                    ],
                    [
                        'id' => 'broadcast',
                        'label' => 'Deel wijziging met je cirkels',
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
                'submit_label' => 'Opslaan',
                'automation' => 'profile.update_profile',
            ],
        ],
        'automations' => [
            [
                'id' => 'profile.update_profile',
                'label' => 'Werk profielgegevens bij',
                'description' => 'Actualiseert de geselecteerde profielvelden en registreert de wijziging.',
                'fields' => [
                    [
                        'id' => 'nickname',
                        'label' => 'Weergavenaam',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'id' => 'broadcast',
                        'label' => 'Deel wijziging met cirkels',
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
                'success_message' => 'Je profiel is bijgewerkt.',
            ],
        ],
    ],
];
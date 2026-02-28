<?php

return [

    'contacto_basico' => [
        'name' => 'Contacto Básico',
        'description' => 'Formulario simple de nombre, email y mensaje.',
        'fields' => [
            [
                'key' => 'name',
                'label' => 'Nombre',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Tu nombre completo',
                'max_length' => 100,
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'placeholder' => 'tu@email.com',
                'max_length' => 255,
            ],
            [
                'key' => 'message',
                'label' => 'Mensaje',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '¿En qué podemos ayudarte?',
                'max_length' => 1000,
            ],
        ],
    ],

    'muebleria' => [
        'name' => 'Mueblería',
        'description' => 'Formulario para tiendas de muebles con interés de producto.',
        'fields' => [
            [
                'key' => 'name',
                'label' => 'Nombre',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Tu nombre completo',
                'max_length' => 100,
            ],
            [
                'key' => 'phone',
                'label' => 'Teléfono',
                'type' => 'tel',
                'required' => true,
                'placeholder' => '10 dígitos',
                'max_length' => 15,
            ],
            [
                'key' => 'product_interest',
                'label' => '¿Qué producto te interesa?',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'salas' => 'Salas',
                    'comedores' => 'Comedores',
                    'recamaras' => 'Recámaras',
                    'cocinas' => 'Cocinas',
                    'oficina' => 'Muebles de Oficina',
                    'otro' => 'Otro',
                ],
            ],
            [
                'key' => 'budget_range',
                'label' => 'Rango de presupuesto',
                'type' => 'select',
                'required' => false,
                'options' => [
                    'bajo' => 'Menos de $5,000',
                    'medio' => '$5,000 - $15,000',
                    'alto' => '$15,000 - $50,000',
                    'premium' => 'Más de $50,000',
                ],
            ],
            [
                'key' => 'message',
                'label' => 'Comentarios adicionales',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => 'Cuéntanos más sobre lo que buscas...',
                'max_length' => 1000,
            ],
        ],
    ],

    'agencia_viajes' => [
        'name' => 'Agencia de Viajes',
        'description' => 'Formulario para agencias de viajes con destino y fechas.',
        'fields' => [
            [
                'key' => 'name',
                'label' => 'Nombre',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Tu nombre completo',
                'max_length' => 100,
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'placeholder' => 'tu@email.com',
                'max_length' => 255,
            ],
            [
                'key' => 'phone',
                'label' => 'Teléfono',
                'type' => 'tel',
                'required' => false,
                'placeholder' => '10 dígitos',
                'max_length' => 15,
            ],
            [
                'key' => 'destination',
                'label' => 'Destino de interés',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Ej: Cancún, Europa, Japón...',
                'max_length' => 200,
            ],
            [
                'key' => 'travel_date',
                'label' => 'Fecha aproximada de viaje',
                'type' => 'date',
                'required' => false,
            ],
            [
                'key' => 'travelers',
                'label' => 'Número de viajeros',
                'type' => 'number',
                'required' => true,
                'min' => 1,
                'max' => 20,
            ],
            [
                'key' => 'trip_type',
                'label' => 'Tipo de viaje',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'playa' => 'Playa / Resort',
                    'aventura' => 'Aventura',
                    'cultural' => 'Cultural',
                    'negocios' => 'Negocios',
                    'luna_miel' => 'Luna de Miel',
                    'familiar' => 'Familiar',
                ],
            ],
        ],
    ],

];

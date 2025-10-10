<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nome da Loja
    |--------------------------------------------------------------------------
    | Nome da sua loja que aparecerá nas mensagens do chatbot
    */
    'store_name' => env('CHATBOT_STORE_NAME', 'Nossa Loja'),

    /*
    |--------------------------------------------------------------------------
    | Configurações de IA
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('CHATBOT_MAX_TOKENS', 200),
        'max_retries' => env('CHATBOT_MAX_RETRIES', 3),
        'temperature' => env('CHATBOT_TEMPERATURE', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Histórico de Mensagens
    |--------------------------------------------------------------------------
    | Quantas mensagens anteriores enviar como contexto para a IA
    */
    'history_limit' => env('CHATBOT_HISTORY_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Textos Personalizados
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'welcome' => env('CHATBOT_WELCOME', 'Bem-vindo! Como posso ajudá-lo?'),
        'error' => env('CHATBOT_ERROR_MESSAGE', 'Desculpe, estou com dificuldades técnicas. Por favor, aguarde um momento.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Informações do Negócio
    |--------------------------------------------------------------------------
    */
    'business' => [
        'delivery_time_min' => env('BUSINESS_DELIVERY_MIN', 15),
        'delivery_time_max' => env('BUSINESS_DELIVERY_MAX', 30),
        'delivery_time_unit' => env('BUSINESS_DELIVERY_UNIT', 'dias'),

        'products' => [
            'camisetas' => 'Camisetas (básicas, polos, dry-fit)',
            'calcas' => 'Calças (sociais, jeans, cargo)',
            'jalecos' => 'Jalecos profissionais',
            'aventais' => 'Aventais personalizados',
            'bones' => 'Bonés e chapéus',
            'outros' => 'Outros uniformes personalizados',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integração WhatsApp
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'phone_number' => env('WHATSAPP_PHONE_NUMBER', ''),
        'business_id' => env('WHATSAPP_BUSINESS_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificações
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'email' => env('CHATBOT_NOTIFICATION_EMAIL', ''),
        'notify_on_budget' => env('CHATBOT_NOTIFY_ON_BUDGET', true),
        'notify_on_design' => env('CHATBOT_NOTIFY_ON_DESIGN', true),
    ],

];

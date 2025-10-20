<?php

namespace App\Enums;

enum CustomerIntent: string
{
    case GREETING = 'greeting';

    case HAS_DESIGN_YES = 'has_design_yes';

    case HAS_DESIGN_NO = 'has_design_no';

    case ASKING_DELIVERY_TIME = 'asking_delivery_time';

    case ASKING_PRICE = 'asking_price';

    case ASKING_PRODUCTS = 'asking_products';

    case PROVIDING_CATEGORY = 'providing_category';

    case GOODBYE = 'goodbye';

    case THANKING = 'thanking';

    case CONFIRMATION_YES = 'confirmation_yes';

    case CONFIRMATION_NO = 'confirmation_no';

    case UNCLEAR = 'unclear';

    case IS_PE = 'is_pe';

    case IS_BR = 'is_br';

    case PROVIDING_MODEL_LINK = 'providing_model_link'; 

    case PROVIDING_QUANTITY = 'providing_quantity';


    public function description(): string
    {
        return match($this) {
            self::GREETING => 'Saudação',
            self::HAS_DESIGN_YES => 'Tem design',
            self::HAS_DESIGN_NO => 'Não tem design',
            self::ASKING_DELIVERY_TIME => 'Perguntando prazo',
            self::ASKING_PRICE => 'Perguntando preço',
            self::ASKING_PRODUCTS => 'Perguntando produtos',
            self::PROVIDING_CATEGORY => 'Fornecendo categoria',
            self::PROVIDING_MODEL_LINK => 'Enviou link de modelo',
            self::PROVIDING_QUANTITY => 'Informou quantidade',
            self::IS_PE => 'Fornecendo uma cidade do estado de PE (Pernambuco)',
            self::IS_BR => 'Fornecendo uma cidade de outro estado do Brasil (fora de Pernambuco)',
            self::GOODBYE => 'Despedida',
            self::THANKING => 'Agradecimento',
            self::CONFIRMATION_YES => 'Confirmação positiva',
            self::CONFIRMATION_NO => 'Confirmação negativa',
            self::UNCLEAR => 'Intenção não clara',
        };
    }
}

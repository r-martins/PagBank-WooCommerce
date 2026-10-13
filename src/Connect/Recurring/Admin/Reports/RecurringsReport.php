<?php

namespace RM_PagBank\Connect\Recurring\Admin\Reports;

use RM_PagBank\Connect\Recurring\Admin\Reports\Block\RecurringsReport as BlockRecurrings;

class RecurringsReport
{

    public static function reportsFilter($reports)
    {
        $reports['pagbank'] = [
            'title' => __('Assinaturas', 'pagbank-connect'),
            'reports' => [
                'recorrencias' => [
                    'title'       => __('Pedidos Recorrentes', 'pagbank-connect'),
                    'description' => __('Pedidos com cobrança recorrente.', 'pagbank-connect'),
                    'hide_title'  => true,
                    'callback'    => [BlockRecurrings::class, 'output']
                ]
            ]
        ];

        return $reports;
    }
   
}


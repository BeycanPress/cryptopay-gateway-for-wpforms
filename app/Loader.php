<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms;

use BeycanPress\CryptoPay\Integrator\Helpers;

class Loader
{
    /**
     * Loader constructor.
     */
    public function __construct()
    {
        Helpers::registerIntegration('wpforms');

        // add transaction page
        Helpers::createTransactionPage(
            esc_html__('WPForms Transactions', 'wpforms-cryptopay'),
            'wpforms',
            10,
            [
                'orderId' => function ($tx) {
                    if (!isset($tx->orderId)) {
                        return esc_html__('Waiting...', 'wpforms-cryptopay');
                    }
                    return Helpers::run('view', 'components/link', [
                        'url' => sprintf(admin_url('admin.php?page=wpforms-payments&view=payment&payment_id=%d'), $tx->orderId), // @phpcs:ignore
                        'text' => sprintf(esc_html__('View payment #%d', 'wpforms-cryptopay'), $tx->orderId)
                    ]);
                }
            ]
        );
    }
}

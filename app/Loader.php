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

        add_action('init', function (): void {
            // add transaction page
            Helpers::createTransactionPage(
                esc_html__('WPForms Transactions', 'cryptopay-gateway-for-wpforms'),
                'wpforms',
                9,
                [
                    'orderId' => function ($tx) {
                        if (!isset($tx->orderId)) {
                            return esc_html__('Waiting...', 'cryptopay-gateway-for-wpforms');
                        }
                        return Helpers::run('view', 'components/link', [
                            'url' => sprintf(admin_url('admin.php?page=wpforms-payments&view=payment&payment_id=%d'), $tx->orderId), // @phpcs:ignore
                            /* translators: %d: transaction order id */
                            'text' => sprintf(esc_html__('View payment #%d', 'cryptopay-gateway-for-wpforms'), $tx->orderId) // phpcs:ignore
                        ]);
                    }
                ]
            );
        });
    }
}

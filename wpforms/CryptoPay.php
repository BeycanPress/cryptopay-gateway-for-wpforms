<?php

declare(strict_types=1);

namespace WPForms\Integrations;

// phpcs:disable PSR1.Methods.CamelCapsMethodName
// phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint

use BeycanPress\CryptoPay\WPForms\Field;
use BeycanPress\CryptoPay\WPForms\Process;
use BeycanPress\CryptoPay\WPForms\Payments;
use BeycanPress\CryptoPay\Integrator\Helpers;
use BeycanPress\CryptoPay\WPForms\Fields\Settings;

final class CryptoPay implements IntegrationInterface
{
    /**
     * @return bool
     */
    public function allow_load(): bool
    {
        return (bool) apply_filters('wpforms_integrations_cryptopay_allow_load', true);
    }

    /**
     * Load the integration.
     * @return void
     */
    public function load(): void
    {
        add_filter(
            'wpforms_db_payments_value_validator_get_allowed_gateways',
            [$this, 'registerGatewayNames']
        );

        add_filter(
            'wpforms_admin_payments_views_single_gateway_transaction_link',
            [$this, 'createTransactionLink'],
            10,
            2
        );

        add_filter(
            'wpforms_helpers_templates_include_html_args',
            [$this, 'hideActionLinks'],
            10,
            3
        );

        $type = Helpers::exists() ? 'cryptopay' : 'cryptopay-lite';
        $name = Helpers::exists() ? 'CryptoPay' : 'CryptoPay Lite';

        new Field($name, $type);
        new Process($name, $type);

        if (wpforms_is_admin_page('settings', 'payments')) {
            new Settings();
        }

        if (wpforms_is_admin_page('builder')) {
            new Payments($name, $type);
        }
    }

    /**
     * @param array<mixed> $gateways
     * @return array<mixed>
     */
    public function registerGatewayNames(array $gateways): array
    {
        return array_merge($gateways, [
            'cryptopay' => esc_html__('CryptoPay', 'wpforms-cryptopay'),
            'cryptopay-lite' => esc_html__('CryptoPay Lite', 'wpforms-cryptopay')
        ]);
    }

    /**
     * @param string $link
     * @param object $payment
     * @return string
     */
    public function createTransactionLink(string $link, object $payment): string
    {
        if (!$this->isOurGateway($payment->gateway)) {
            return $link;
        }

        return sprintf(
            admin_url('admin.php?page=cryptopay_lite_wpforms_transactions&s=%s'),
            $payment->transaction_id // phpcs:ignore
        );
    }

    /**
     * @param array<mixed> $args
     * @param string $templateName
     * @param bool $extract
     * @return array<mixed>
     */
    public function hideActionLinks(array $args, string $templateName, bool $extract): array
    {
        if ('admin/payments/single/payment-details.php' !== $templateName) {
            return $args;
        }

        if ($this->isOurGateway($args['gateway_name'])) {
            $args['disabled'] = true;
        }

        return $args;
    }

    /**
     * @param string $gateway
     * @return bool
     */
    private function isOurGateway(string $gateway): bool
    {
        return in_array($gateway, ['cryptopay', 'cryptopay-lite'], true);
    }
}

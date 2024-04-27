<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms;

use BeycanPress\CryptoPay\Integrator\Hook;
use BeycanPress\CryptoPay\Integrator\Helpers;
use BeycanPress\CryptoPay\Integrator\Session;

class Process
{
    /**
     * @param string $name
     * @param string $type
     * Loader constructor.
     */
    public function __construct(private string $name, private string $type)
    {
        Hook::addFilter('before_payment_finished_wpforms', [$this, 'paymentFinished']);
        Hook::addFilter('payment_redirect_urls_wpforms', [$this, 'paymentRedirectUrls']);
        add_action('wpforms_process_initial_errors', [$this, 'paymentCheckProcess'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'paymentConfirmationProcess'], 10, 4);
    }

    /**
     * @param string $tx
     * @param array<mixed> $fields
     * @return array<mixed>
     */
    private function setTxToOurField(string $tx, array $fields): array
    {
        $index = array_search($this->type, array_column($fields, 'type', 'id'));
        $fields[$index]['name'] = esc_html__('Transaction Hash', 'wpforms-cryptopay');
        $fields[$index]['value'] = $tx;

        return $fields;
    }

    /**
     * @param int $formId
     * @param array<mixed> $entry
     * @return array<mixed>
     */
    private function createFieldsWithValue(int $formId, array $entry): array
    {
        $fields = [];
        $form = wpforms()->get('form')->get($formId);
        $formData = wpforms_decode($form->post_content); // phpcs:ignore

        foreach ((array) $formData['fields'] as $fieldProperties) {
            $fieldId     = $fieldProperties['id'];
            $fieldType   = $fieldProperties['type'];
            $fieldSubmit = isset($entry['fields'][$fieldId]) ? $entry['fields'][$fieldId] : '';

            if (is_array($fieldSubmit)) {
                $fieldSubmit = array_filter($fieldSubmit);
                $fieldSubmit = implode(" ", $fieldSubmit);
            }

            $name = !empty($formData['fields'][$fieldId]['label'])
            ? sanitize_text_field($formData['fields'][$fieldId]['label'])
            : '';

            // Sanitize but keep line breaks.
            $value = wpforms_sanitize_textarea_field($fieldSubmit);

            $fields[$fieldId] = [
                'name'  => $name,
                'value' => $value,
                'id'    => absint($fieldId),
                'type'  => $fieldType,
            ];
        }

        return $fields;
    }

    /**
     * @param array<mixed> $errors
     * @param array<mixed> $formData
     * @return array<mixed>
     */
    public function paymentCheckProcess(array $errors, array $formData): array
    {
        if ($this->hashCryptoPayField($formData)) {
            // Nonce process in WPForms side
            $transactionHash = isset($_POST['wpforms']['transaction-hash'])
                ? sanitize_text_field($_POST['wpforms']['transaction-hash'])
                : '';

            $model = Helpers::run('getModelByAddon', 'wpforms');

            $transaction = $model->findOneBy([
                'hash' => $transactionHash,
            ]);

            if (!$transaction) {
                $errors[$formData['id']] = [
                    'header' => esc_html__(
                        'Payment is not verified. Sending form has been aborted.',
                        'wpforms-cryptopay'
                    )
                ];
            }
        }

        return $errors;
    }

    /**
     * @param array<mixed>  $fields    Fields data.
     * @param array<mixed>  $entry     Form submission raw data ($_POST).
     * @param array<mixed>  $formData  Form data.
     * @param int           $entryId   Entry ID.
     * @return void
     */
    public function paymentConfirmationProcess(array $fields, array $entry, array $formData, int $entryId): void
    {
        $paymentId = Session::get('wpforms_payment_id');

        if ($entryId) {
            wpforms()->get('payment')->update(
                $paymentId,
                [
                    'entry_id' => $entryId,
                ]
            );
        }

        wpforms()->get('payment_meta')->bulk_add(
            $paymentId,
            [
                'fields' => wp_json_encode($this->setTxToOurField(Session::get('wpforms_transaction_hash'), $fields)),
            ]
        );

        Session::remove('wpforms_payment_id');
        Session::remove('wpforms_transaction_hash');
    }

    /**
     * @param object $data
     * @return object
     */
    public function paymentFinished(object $data): object
    {
        $order = $data->getOrder();
        $formId = $data->getParams()->get('formId');
        $testnet = Helpers::run('getTestnetStatus');
        $userData = get_userdata($data->getUserId());

        $paymentId = wpforms()->get('payment')->add([
            'is_published'     => 1,
            'gateway'          => $this->type,
            'type'             => 'one-time',
            'form_id'          => $formId ?? 0,
            'entry_id'         => $entryId ?? 0,
            'customer_id'      => $userData->ID,
            'mode'             => $testnet ? 'test' : 'live',
            'subtotal_amount'  => $order->getAmount(),
            'total_amount'     => $order->getAmount(),
            'currency'         => $order->getCurrency(),
            'date_created_gmt' => gmdate('Y-m-d H:i:s'),
            'date_updated_gmt' => gmdate('Y-m-d H:i:s'),
            'transaction_id'   => substr($data->getHash(), 0, 40),
            'title'            => $userData->user_login, // @phpcs:ignore
            'status'           => $data->getStatus() ? 'completed' : 'failed'
        ]);

        wpforms()->get('payment_meta')->bulk_add(
            $paymentId,
            [
                'user_id' => $userData->ID,
                'ip_address' => Helpers::run('getIp'),
                'method_type' => $order->getPaymentCurrency()->getSymbol(),
                'customer_name' => $userData->display_name, // @phpcs:ignore
                'customer_email' => $userData->user_email, // @phpcs:ignore
                'log' => [
                    'value' => esc_html__('Payment is completed.', 'wpforms-cryptopay'),
                    'date' => gmdate('Y-m-d H:i:s'),
                ]
            ]
        );

        $order->setId(intval($paymentId));
        Session::set('wpforms_payment_id', $paymentId);
        Session::set('wpforms_transaction_hash', $data->getHash());

        return $data;
    }

    /**
     * @param object $data
     * @return array<string>
     */
    public function paymentRedirectUrls(object $data): array
    {
        return [
            'success' => '#success',
            'failed' => '#failed'
        ];
    }

    /**
     * @param array<mixed> $forms
     * @param bool $multiple
     * @return bool
     */
    public function hashCryptoPayField(array $forms, bool $multiple = false): bool
    {
        return false !== wpforms_has_field_type($this->type, $forms, $multiple);
    }

    /**
     * @param string $message
     * @param array<mixed> $formData
     * @return void
     */
    private function returnError(string $message, array $formData): void
    {
        ob_start();
        wpforms()->get('frontend')->form_error('header', $message, $formData);
        $error = ob_get_clean();
        wp_send_json([
            'success' => false,
            'data' => [
                'errors' => [
                    'general' => [
                        'header' => $error
                    ]
                ]
            ],
        ]);
        exit;
    }
}

<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms;

// phpcs:disable PSR1.Methods.CamelCapsMethodName
// phpcs:disable Squiz.NamingConventions.ValidVariableName
// phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint

use BeycanPress\CryptoPay\Payment;
use BeycanPress\CryptoPay\Integrator\Hook;
use BeycanPress\CryptoPay\Integrator\Helpers;
use BeycanPress\CryptoPay\Integrator\Session;
use BeycanPress\CryptoPayLite\Payment as PaymentLite;

class Field extends \WPForms_Field
{
    /**
     * @var string
     */
    public const FIELD_PREVIEW_CVC_ICON_SVG = WPFORMS_CRYPTOPAY_SVG_ICON;

    /**
     * @var string
     */
    public $form_data;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $keywords;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $icon;

    /**
     * @var int
     */
    public $order;

    /**
     * @var string
     */
    public $group;

    /**
     * @param string $name
     * @param string $type
     */
    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
        parent::__construct();
    }

    /**
     * @return void
     */
    public function init(): void
    {
        $this->order    = 90;
        $this->group    = 'payment';
        $this->icon     = 'fa fa-bitcoin';
        $this->keywords = esc_html__('store, ecommerce, crypto, pay, payment, bitcoin', 'wpforms-cryptopay');

        // Define additional field properties.
        add_filter('wpforms_builder_fields_options', [$this, 'preFieldsOptions']);
        add_filter('wpforms_field_properties_' . $this->type, [$this, 'fieldProperties'], 5, 3);

        // Set field to required by default.
        add_filter('wpforms_field_new_required', [$this, 'defaultRequired'], 10, 2);

        add_action('wpforms_builder_enqueues', [$this, 'builderEnqueues']);
        add_filter('wpforms_builder_strings', [$this, 'builderJsStrings'], 10, 2);
        add_filter('wpforms_builder_field_button_attributes', [$this, 'fieldButtonAttributes'], 10, 3);
        add_filter('wpforms_frontend_foot_submit_classes', [$this, 'hideSubmitButtonIfFieldExists'], 10, 2);
        add_filter('wpforms_field_new_display_duplicate_button', [$this, 'fieldDisplayDuplicateButton'], 10, 2);
        add_filter('wpforms_field_preview_display_duplicate_button', [$this, 'fieldDisplayDuplicateButton'], 10, 2);
    }

    /**
     * @param array<mixed> $properties
     * @param array<mixed> $field
     * @param array<mixed> $formData
     * @return array<mixed>
     */
    public function fieldProperties($properties, $field, $formData): array
    {
        $this->form_data = $formData;
        return $properties;
    }

    /**
     * @param object $form
     * @return void
     */
    public function preFieldsOptions($form): void
    {
        if (!isset($form->post_content)) {
            $this->form_data = [];

            return;
        }

        $this->form_data = $form ? wpforms_decode($form->post_content) : [];

        if (!is_array($this->form_data)) {
            $this->form_data = [];
        }
    }

    /**
     * @param bool $required
     * @param array<mixed> $field
     * @return bool
     */
    public function defaultRequired($required, $field): bool
    {
        return boolval($field['type'] === $this->type ? true : $required);
    }

    /**
     * @param string $view current view
     * @return void
     */
    public function builderEnqueues($view): void
    {
        wp_enqueue_script(
            'wpforms-builder-cryptopay',
            WPFORMS_CRYPTOPAY_URL . "assets/js/admin.js",
            ['jquery', 'wpforms-builder'],
            WPFORMS_CRYPTOPAY_VERSION,
            true
        );

        wp_localize_script(
            'wpforms-builder-cryptopay',
            'wpforms_builder_cryptopay',
            [
                'type' => $this->type,
            ]
        );
    }

    /**
     * @param array<string,string> $strings Form builder JS strings.
     * @param array<mixed> $form    Form data.
     * @return array<string,string>
     */
    public function builderJsStrings($strings, $form): array
    {
        $strings['cp_payments_enabled_required'] = wp_kses(
            str_replace('{name}', $this->name, __('<p>{name} must be enabled in the settings when using the field.</p><p>To proceed, please go to <strong>Payments » {name}</strong> and check <strong>Enable {name}</strong>.</p>', 'wpforms-cryptopay')), // phpcs:ignore
            [
                'p'      => [],
                'strong' => [],
            ]
        );

        $strings['cp_total_field_not_found'] = wp_kses(
            str_replace('{name}', $this->name, __('<p>{name} requires a Payment Total field to be added to the form.</p><p>To proceed, please add a <strong>Payment Total</strong> field to the form.</p>', 'wpforms-cryptopay')), // phpcs:ignore
            [
                'p'      => [],
                'strong' => [],
            ]
        );

        return $strings;
    }

    /**
     * Define additional "Add Field" button attributes.
     *
     * @since 1.8.2
     *
     * @param array<string,mixed> $attributes Button attributes.
     * @param array<mixed> $field             Field settings.
     * @param array<mixed> $formData          Form data and settings.
     *
     * @return array<string,mixed>
     */
    public function fieldButtonAttributes($attributes, $field, $formData): array
    {
        if ($this->type !== $field['type']) {
            return $attributes;
        }

        if ($this->hasIsField($formData)) {
            $attributes['atts']['disabled'] = 'true';

            return $attributes;
        }

        return $attributes;
    }

    /**
     * @param array<string> $classes
     * @param array<mixed> $formData
     * @return array<mixed>
     */
    public function hideSubmitButtonIfFieldExists($classes, $formData): array
    {
        if ($this->hasIsField($formData) && !Session::has('wpforms_transaction_hash')) {
            $classes[] = 'wpforms-hidden';
        }

        return $classes;
    }

    /**
     * @param array<mixed> $forms
     * @param bool $multiple
     * @return bool
     */
    public function hasIsField($forms, $multiple = false): bool
    {
        return false !== wpforms_has_field_type($this->type, $forms, $multiple);
    }

    /**
     * Define if "Duplicate" button has to be displayed on field preview in a Form Builder.
     * @param bool         $display Display switch.
     * @param array<mixed> $field   Field settings.
     * @return bool
     */
    public function fieldDisplayDuplicateButton($display, $field): bool
    {
        return $this->type === $field['type'] ? false : $display;
    }

    /**
     * @return bool
     */
    private function isBlockEditor(): bool
    {
        $isGutenberg = false;
        $isElementor = false;
        $isDivi      = false;

        // Nonce process in WPForms side
        $etFb = isset($_GET['et_fb']) ? sanitize_text_field($_GET['et_fb']) : '';
        $getAction = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $postAction = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
        $reqContext = isset($_REQUEST['context']) ? sanitize_text_field($_REQUEST['context']) : '';

        if (!empty($postAction)) {
            if (!empty($etFb) && 'wpforms_divi_preview' === $postAction) {
                $isDivi = true;
            }

            if ('elementor_ajax' === $postAction || 'elementor' === $getAction) {
                $isElementor = true;
            }
        }

        if (!empty($reqContext)) {
            if (defined('REST_REQUEST') && REST_REQUEST && 'edit' === $reqContext) {
                $isGutenberg = true;
            }
        }

        return $isGutenberg || $isElementor || $isDivi;
    }

    /**
     * @param array<mixed> $field
     * @return void
     */
    public function field_options($field): void
    {
        $this->field_option('basic-options', $field, ['markup' => 'open']);

        $this->field_element('row', $field, [
            'slug'    => 'show_price_after_labels',
            'content' => $this->field_element(
                'select',
                $field,
                [
                    'slug'    => 'theme',
                    'value'   => !empty($field['theme']) ? esc_attr($field['theme']) : 'light',
                    'options' => [
                        'light' => esc_html__('Light', 'wpforms-cryptopay'),
                        'dark'  => esc_html__('Dark', 'wpforms-cryptopay'),
                    ],
                ],
                false
            ),
        ]);

        $this->field_option('basic-options', $field, ['markup' => 'close']);
    }

    /**
     * Field preview inside the builder.
     * @param array<mixed> $field Field settings.
     * @return void
     */
    public function field_preview($field): void
    {
        $this->field_preview_option('label', [
            'label' => $this->name
        ]);

        echo esc_html(str_replace(
            '{name}',
            $this->name,
            esc_html__('{name} does not have a preview and also removes the submit button because it starts the submit process after the payment is made. You can see what {name} looks like on the page where you add the form.', 'wpforms-cryptopay') // phpcs:ignore
        ));
    }

    /**
     * Field display on the form front-end.
     * @param array<mixed> $field      Field data and settings.
     * @param array<mixed> $deprecated Deprecated field attributes. Use field properties.
     * @param array<mixed> $formData  Form data and settings.
     * @return void
     */
    public function field_display($field, $deprecated, $formData): void
    {
        if ($this->isBlockEditor()) {
            $this->field_preview($field);
            return;
        }

        $formId = absint($formData['id']);
        $theme = !empty($field['theme']) ? esc_attr($field['theme']) : 'light';

        $totalFieldExists = wpforms_has_field_type('payment-total', $formData);

        if (!$totalFieldExists) {
            echo wp_kses(
                sprintf(
                    '<div class="wpforms-error">%s</div>',
                    esc_html__(
                        'Payment Total field is required for CryptoPay Lite field to work properly.',
                        'wpforms-cryptopay'
                    )
                ),
                [
                    'div' => [
                        'class' => [],
                    ],
                ]
            );
            return;
        }

        if (Session::has('wpforms_transaction_hash')) {
            $model = Helpers::run('getModelByAddon', 'wpforms');
            $transaction = $model->findOneBy([
                'hash' => Session::get('wpforms_transaction_hash'),
            ]);
            $formId = $transaction->getParams()->get('formId');

            if ('verified' === $transaction->getStatus()->getValue() && $formId === $formData['id']) {
                echo wp_kses(
                    sprintf(
                        '<p>%s</p>',
                        esc_html__('A payment has already been made for this form, but the form has not been sent. Therefore please only submit the form.', 'wpforms-cryptopay') // phpcs:ignore
                    ),
                    [
                        'p' => [],
                    ]
                );
                echo wp_kses(
                    sprintf(
                        '<input type="hidden" name="wpforms[transaction-hash]" value="%s" />',
                        esc_attr($transaction->getHash())
                    ),
                    [
                        'input' => [
                            'type'  => [],
                            'name'  => [],
                            'value' => [],
                        ],
                    ]
                );
                return;
            }
        }

        Hook::addFilter('theme', function (array $themeOptions) use ($theme) {
            $themeOptions['mode'] = $theme ?? 'light';
            return $themeOptions;
        });

        Hook::addFilter('edit_config_data_wpforms', function (object $config) {
            return $config->disableReminderEmail();
        });

        if (Helpers::exists()) {
            $html = (new Payment('wpforms'))->html();
        } else {
            $html = (new PaymentLite('wpforms'))->html();
        }

        wp_enqueue_script(
            'wpforms-cryptopay',
            WPFORMS_CRYPTOPAY_URL . 'assets/js/main.js',
            ['jquery', Helpers::run('getProp', 'mainJsKey')],
            WPFORMS_CRYPTOPAY_VERSION,
            true
        );

        wp_localize_script(
            'wpforms-cryptopay',
            'wpforms_cryptopay',
            [
                'type' => $this->type,
                'amountMustBeGreaterThanZero' => esc_html__('Your order amount must be greater than 0 for the payment section to be active.', 'wpforms-cryptopay'), // phpcs:ignore,
                'paymentCompletedMessage'     => esc_html__('Payment completed successfully.', 'wpforms-cryptopay'),
            ]
        );

        Helpers::run('ksesEcho', $html);
    }
}

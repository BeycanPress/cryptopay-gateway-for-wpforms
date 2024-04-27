<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms;

// phpcs:disable Generic.Files.InlineHTML
// phpcs:disable Generic.Files.LineLength
// phpcs:disable PSR1.Methods.CamelCapsMethodName
// phpcs:disable Squiz.NamingConventions.ValidVariableName
// phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint

use BeycanPress\CryptoPay\Integrator\Helpers;

class Payments
{
    /**
     * @var string
     */
    private $slug;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $recommended = true;

    /**
     * @var string
     */
    private $icon = '';

    /**
     * @var array $form_data
     */
    private $form_data = [];

    /**
     * @param string $name
     * @param string $slug
     * Initialize the class.
     */
    public function __construct(string $name, string $slug)
    {
        $this->name      = $name;
        $this->slug      = $slug;
        $this->icon      = WPFORMS_CRYPTOPAY_URL . 'assets/images/icon.svg';
        $this->form_data = $this->getFormData();

        add_filter('wpforms_payments_available', [$this, 'registerPayment']);
        add_action('wpforms_payments_panel_content', [$this, 'builderOutput'], 0);
        add_action('wpforms_payments_panel_sidebar', [$this, 'builderSidebar'], 0);
        add_filter(
            'wpforms_admin_education_addons_item_base_display_single_addon_hide',
            [$this, 'shouldHideEducationalMenuItem'],
            10,
            2
        );
    }

    /**
     * @return array<mixed>
     */
    private function getFormData(): array
    {
        // Nonce process in WPForms side
        $formId = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;

        if (!$formId) {
            return [];
        }

        $formData = wpforms()->get('form')->get(
            $formId,
            [
                'content_only' => true,
            ]
        );

        return is_array($formData) ? $formData : [];
    }

    /**
     * @param array<string> $paymentsAvailable List of available payment gateways.
     * @return array<string>
     */
    public function registerPayment($paymentsAvailable): array
    {

        $paymentsAvailable[$this->slug] = $this->name;

        return $paymentsAvailable;
    }

    /**
     * @return void
     */
    public function builderOutput(): void
    {
        ?>
        <div class="wpforms-panel-content-section wpforms-panel-content-section-<?php echo esc_attr($this->slug); ?>" id="<?php echo esc_attr($this->slug); ?>-provider" data-provider="<?php echo esc_attr($this->slug); ?>">

            <div class="wpforms-panel-content-section-title">
                <?php echo esc_html($this->name); ?>
            </div>

            <div class="wpforms-payment-settings wpforms-clear">
                <?php $this->builderContent(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * @return void
     */
    public function builderContent(): void
    {
        echo '<div id="wpforms-panel-content-section-payment-' . esc_attr($this->slug) . '">';

        ?>
        <div class="wpforms-panel-content-section-payment">
            <h2 class="wpforms-panel-content-section-payment-subtitle">
                <?php echo esc_html($this->name); ?>
            </h2>
            <?php
                wpforms_panel_field(
                    'toggle',
                    $this->slug,
                    'enable',
                    $this->form_data,
                    sprintf(
                        /* translators: %s - payment gateway name */
                        esc_html__('Enable %s', 'wpforms-lite'),
                        $this->name
                    ),
                    [
                        'parent'  => 'payments',
                        'default' => '0',
                        'tooltip' => esc_html__('Allow your customers to cryptocurrency payments via the form.', 'wpforms-cryptopay'),
                        'class'   => 'wpforms-panel-content-section-payment-toggle wpforms-panel-content-section-payment-toggle-' . esc_attr($this->slug),
                    ]
                );
            ?>
        </div>
        <?php

        echo '</div>';
    }

    /**
     * @return void
     */
    public function builderSidebar(): void
    {
        Helpers::run('ksesEcho', wpforms_render(
            'builder/payment/sidebar',
            [
                'configured'  => 'configured',
                'slug'        => $this->slug,
                'icon'        => $this->icon,
                'name'        => $this->name,
                'recommended' => $this->recommended,
            ],
            true
        ));
    }

    /**
     * @param bool         $hide  Whether to hide the menu item.
     * @param array<mixed> $addon Addon data.
     * @return bool
     */
    public function shouldHideEducationalMenuItem($hide, $addon): bool
    {
        return isset($addon['clear_slug']) && $this->slug === $addon['clear_slug'] ? true : $hide;
    }
}

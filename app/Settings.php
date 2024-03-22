<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms;

use BeycanPress\CryptoPay\Integrator\Helpers;

class Settings
{
    /**
     * SettingsLite constructor.
     */
    public function __construct()
    {
        add_filter('wpforms_settings_defaults', [$this, 'registerFields'], 6);
    }

    /**
     * @param array<mixed> $settings
     * @return array<mixed>
     */
    public function registerFields(array $settings): array
    {
        if (!isset($settings['payments'])) {
            return $settings;
        }

        $key = Helpers::exists() ? 'cryptopay' : 'cryptopay-lite';
        $title = Helpers::exists() ? 'CryptoPay' : 'CryptoPay Lite';

        $fields = [
            $key . '-heading' => [
                'id'       => $key . '-heading',
                'content'  => $this->getHeadingContent($title),
                'type'     => 'content',
                'no_label' => true,
                'class'    => ['section-heading'],
            ],
        ];

        $settings['payments'] = array_merge($settings['payments'], $fields);

        return $settings;
    }

    /**
     * @param string $title
     * @return string
     */
    private function getHeadingContent(string $title): string
    {
        return '<h4>' . $title . '</h4>' .
            '<p>' .
            str_replace(
                '{title}',
                $title,
                esc_html__(
                    // phpcs:ignore
                    '{title} Settings are managed from payments section on form builder and {title} field settings.',
                    'wpforms-cryptopay'
                )
            ) .
            '</p>';
    }
}

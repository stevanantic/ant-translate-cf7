<?php
/**
 * ANT Translate for Contact Form 7 – License handler (free addon).
 *
 * Extends ANT_Addon_License_Base but overrides is_active() to always
 * return true. This addon is free and does not require a paid license.
 * The class exists so the Settings → Addons card renders correctly
 * (showing "Licensed" status instead of a confusing license input form).
 *
 * @package ANT_Translate_CF7
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ANT_CF7_License extends ANT_Addon_License_Base
{
    /** @var self|null */
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function product_slug(): string
    {
        return 'ant-translate-cf7';
    }

    protected function option_key(): string
    {
        return 'ant_st_addon_lic_cf7';
    }

    protected function addon_name(): string
    {
        return 'ANT Translate for Contact Form 7';
    }

    /**
     * Always active – this is a free addon, no paid license required.
     */
    public function is_active(): bool
    {
        return true;
    }

    /**
     * Return a "free" masked key indicator for the UI.
     */
    public function get_masked_key(): string
    {
        return 'free';
    }
}

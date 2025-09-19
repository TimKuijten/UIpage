<?php
/**
 * Plugin Name: Kovacic Language Switcher
 * Description: Adds a lightweight language switcher to individual pages and lets editors provide a translated HTML version of the content.
 * Version: 1.1.0
 * Author: Kovacic Talent
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kovacic_Language_Switcher {
    private const META_KEY = '_kls_translations';
    private ?array $current_settings = null;
    private bool $portal_rendered = false;

    public function __construct() {
        add_action('init', [$this, 'register_assets']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post_page', [$this, 'save_meta_box']);
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('template_redirect', [$this, 'prepare_frontend']);
    }

    public function register_assets(): void {
        $version = '1.1.0';
        $base_url = plugin_dir_url(__FILE__);

        wp_register_style(
            'kls-switcher',
            $base_url . 'assets/css/translation-switcher.css',
            [],
            $version
        );

        wp_register_script(
            'kls-switcher',
            $base_url . 'assets/js/translation-switcher.js',
            [],
            $version,
            true
        );
    }

    public function register_meta_box(): void {
        add_meta_box(
            'kls_translation_box',
            __('Language switcher – Spanish translation', 'kovacic-language-switcher'),
            [$this, 'render_meta_box'],
            'page',
            'normal',
            'default'
        );
    }

    private function get_page_settings(int $post_id): array {
        $saved = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($saved) || empty($saved['spanish_content'])) {
            return [];
        }

        return [
            'enabled' => !empty($saved['enabled']),
            'default_language' => in_array($saved['default_language'] ?? 'en', ['en', 'es'], true) ? $saved['default_language'] : 'en',
            'english_label' => $saved['english_label'] ?? __('English', 'kovacic-language-switcher'),
            'spanish_label' => $saved['spanish_label'] ?? __('Español', 'kovacic-language-switcher'),
            'spanish_content' => $saved['spanish_content'],
        ];
    }

    private function build_defaults(array $settings = []): array {
        return [
            'enabled' => !empty($settings['spanish_content']) || !empty($settings['enabled']),
            'default_language' => $settings['default_language'] ?? 'en',
            'english_label' => $settings['english_label'] ?? __('English', 'kovacic-language-switcher'),
            'spanish_label' => $settings['spanish_label'] ?? __('Español', 'kovacic-language-switcher'),
            'spanish_content' => $settings['spanish_content'] ?? '',
        ];
    }

    public function render_meta_box(\WP_Post $post): void {
        $defaults = $this->build_defaults($this->get_page_settings($post->ID));

        wp_nonce_field('kls_translation_box', 'kls_translation_nonce');
        ?>
        <p><?php esc_html_e('Provide a Spanish HTML version of this page. The original page content is treated as English.', 'kovacic-language-switcher'); ?></p>
        <p>
            <label for="kls_switcher_enabled">
                <input type="checkbox" id="kls_switcher_enabled" name="kls_switcher[enabled]" value="1" <?php checked($defaults['enabled']); ?> />
                <?php esc_html_e('Enable the language switcher on this page', 'kovacic-language-switcher'); ?>
            </label>
        </p>
        <p>
            <label for="kls_default_language" class="screen-reader-text"><?php esc_html_e('Default language', 'kovacic-language-switcher'); ?></label>
            <strong><?php esc_html_e('Default language shown to visitors', 'kovacic-language-switcher'); ?></strong>
            <select name="kls_switcher[default_language]" id="kls_default_language">
                <option value="en" <?php selected($defaults['default_language'], 'en'); ?>><?php echo esc_html($defaults['english_label']); ?></option>
                <option value="es" <?php selected($defaults['default_language'], 'es'); ?>><?php echo esc_html($defaults['spanish_label']); ?></option>
            </select>
        </p>
        <p>
            <label for="kls_english_label"><?php esc_html_e('English label', 'kovacic-language-switcher'); ?></label><br />
            <input type="text" id="kls_english_label" name="kls_switcher[english_label]" value="<?php echo esc_attr($defaults['english_label']); ?>" class="widefat" />
        </p>
        <p>
            <label for="kls_spanish_label"><?php esc_html_e('Spanish label', 'kovacic-language-switcher'); ?></label><br />
            <input type="text" id="kls_spanish_label" name="kls_switcher[spanish_label]" value="<?php echo esc_attr($defaults['spanish_label']); ?>" class="widefat" />
        </p>
        <p>
            <label for="kls_spanish_content"><strong><?php esc_html_e('Spanish translation (HTML allowed)', 'kovacic-language-switcher'); ?></strong></label>
            <textarea id="kls_spanish_content" name="kls_switcher[spanish_content]" rows="12" class="widefat code"><?php echo esc_textarea($defaults['spanish_content']); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e('Paste the Spanish HTML markup here. Scripts and inline styles are kept intact for administrators.', 'kovacic-language-switcher'); ?>
        </p>
        <?php
    }

    public function save_meta_box(int $post_id): void {
        if (!isset($_POST['kls_translation_nonce']) || !wp_verify_nonce($_POST['kls_translation_nonce'], 'kls_translation_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_page', $post_id)) {
            return;
        }

        $raw = $_POST['kls_switcher'] ?? null;
        if (!$raw) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        $this->persist_page_settings($post_id, $raw);
    }

    private function prepare_spanish_content(string $content): string {
        if (current_user_can('unfiltered_html')) {
            return $content;
        }

        return wp_kses_post($content);
    }

    private function persist_page_settings(int $post_id, array $raw): void {
        $raw = wp_unslash($raw);

        $enabled = !empty($raw['enabled']);
        $default_language = in_array($raw['default_language'] ?? 'en', ['en', 'es'], true) ? $raw['default_language'] : 'en';

        $data = [
            'enabled' => $enabled,
            'default_language' => $default_language,
            'english_label' => sanitize_text_field($raw['english_label'] ?? __('English', 'kovacic-language-switcher')),
            'spanish_label' => sanitize_text_field($raw['spanish_label'] ?? __('Español', 'kovacic-language-switcher')),
            'spanish_content' => $enabled ? $this->prepare_spanish_content($raw['spanish_content'] ?? '') : '',
        ];

        if (!$data['enabled'] || empty($data['spanish_content'])) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        update_post_meta($post_id, self::META_KEY, $data);
    }

    public function register_admin_page(): void {
        add_options_page(
            __('Language Switcher', 'kovacic-language-switcher'),
            __('Language Switcher', 'kovacic-language-switcher'),
            'manage_options',
            'kls-language-switcher',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            check_admin_referer('kls_save_translations', 'kls_nonce');

            $payload = $_POST['kls_switcher'] ?? [];
            $saved = 0;

            foreach ($payload as $page_id => $data) {
                $page_id = (int) $page_id;

                if ($page_id <= 0 || !current_user_can('edit_page', $page_id)) {
                    continue;
                }

                $this->persist_page_settings($page_id, (array) $data);
                $saved++;
            }

            if ($saved > 0) {
                add_settings_error('kls_switcher', 'kls_switcher_saved', sprintf(_n('%d translation updated.', '%d translations updated.', $saved, 'kovacic-language-switcher'), $saved), 'updated');
            } else {
                add_settings_error('kls_switcher', 'kls_switcher_none', __('No translations were changed.', 'kovacic-language-switcher'), 'notice-info');
            }
        }

        settings_errors('kls_switcher');

        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Language Switcher', 'kovacic-language-switcher'); ?></h1>
            <p><?php esc_html_e('Provide Spanish HTML versions for pages built in the Site Editor.', 'kovacic-language-switcher'); ?></p>
            <form method="post">
                <?php wp_nonce_field('kls_save_translations', 'kls_nonce'); ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Page', 'kovacic-language-switcher'); ?></th>
                            <th><?php esc_html_e('Settings', 'kovacic-language-switcher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if (empty($pages)) :
                        ?>
                        <tr>
                            <td colspan="2"><?php esc_html_e('No pages found.', 'kovacic-language-switcher'); ?></td>
                        </tr>
                        <?php
                    else :
                        foreach ($pages as $page) :
                            $defaults = $this->build_defaults($this->get_page_settings($page->ID));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html(get_the_title($page)); ?></strong>
                                    <p class="description"><?php echo esc_html(get_permalink($page)); ?></p>
                                </td>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="kls_switcher[<?php echo esc_attr($page->ID); ?>][enabled]" value="1" <?php checked($defaults['enabled']); ?> />
                                            <?php esc_html_e('Enable translation', 'kovacic-language-switcher'); ?>
                                        </label>
                                    </fieldset>
                                    <p>
                                        <label for="kls_default_language_<?php echo esc_attr($page->ID); ?>"><strong><?php esc_html_e('Default language', 'kovacic-language-switcher'); ?></strong></label><br />
                                        <select id="kls_default_language_<?php echo esc_attr($page->ID); ?>" name="kls_switcher[<?php echo esc_attr($page->ID); ?>][default_language]">
                                            <option value="en" <?php selected($defaults['default_language'], 'en'); ?>><?php echo esc_html($defaults['english_label']); ?></option>
                                            <option value="es" <?php selected($defaults['default_language'], 'es'); ?>><?php echo esc_html($defaults['spanish_label']); ?></option>
                                        </select>
                                    </p>
                                    <p>
                                        <label for="kls_english_label_<?php echo esc_attr($page->ID); ?>"><?php esc_html_e('English label', 'kovacic-language-switcher'); ?></label><br />
                                        <input type="text" class="regular-text" id="kls_english_label_<?php echo esc_attr($page->ID); ?>" name="kls_switcher[<?php echo esc_attr($page->ID); ?>][english_label]" value="<?php echo esc_attr($defaults['english_label']); ?>" />
                                    </p>
                                    <p>
                                        <label for="kls_spanish_label_<?php echo esc_attr($page->ID); ?>"><?php esc_html_e('Spanish label', 'kovacic-language-switcher'); ?></label><br />
                                        <input type="text" class="regular-text" id="kls_spanish_label_<?php echo esc_attr($page->ID); ?>" name="kls_switcher[<?php echo esc_attr($page->ID); ?>][spanish_label]" value="<?php echo esc_attr($defaults['spanish_label']); ?>" />
                                    </p>
                                    <p>
                                        <label for="kls_spanish_content_<?php echo esc_attr($page->ID); ?>"><?php esc_html_e('Spanish HTML', 'kovacic-language-switcher'); ?></label>
                                        <textarea class="large-text code" rows="10" id="kls_spanish_content_<?php echo esc_attr($page->ID); ?>" name="kls_switcher[<?php echo esc_attr($page->ID); ?>][spanish_content]"><?php echo esc_textarea($defaults['spanish_content']); ?></textarea>
                                    </p>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    endif;
                    ?>
                    </tbody>
                </table>
                <?php submit_button(__('Save translations', 'kovacic-language-switcher')); ?>
            </form>
        </div>
        <?php
    }

    public function prepare_frontend(): void {
        if (!is_singular('page')) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $settings = $this->get_page_settings($post_id);
        if (empty($settings) || empty($settings['enabled'])) {
            return;
        }

        $this->current_settings = $settings;

        wp_enqueue_style('kls-switcher');
        wp_enqueue_script('kls-switcher');

        add_action('wp_body_open', [$this, 'render_switcher_portal'], 5);
    }

    public function render_switcher_portal(): void {
        if ($this->portal_rendered || empty($this->current_settings)) {
            return;
        }

        $this->portal_rendered = true;

        $default = $this->current_settings['default_language'] ?? 'en';
        $default = in_array($default, ['en', 'es'], true) ? $default : 'en';
        ?>
        <div id="kls-switcher-root" class="kls-switcher-portal" data-default="<?php echo esc_attr($default); ?>" data-english-label="<?php echo esc_attr($this->current_settings['english_label']); ?>" data-spanish-label="<?php echo esc_attr($this->current_settings['spanish_label']); ?>" data-label="<?php echo esc_attr__('Language selector', 'kovacic-language-switcher'); ?>">
            <template><?php echo $this->current_settings['spanish_content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
        </div>
        <?php
    }
}

new Kovacic_Language_Switcher();

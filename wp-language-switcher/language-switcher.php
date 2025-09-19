<?php
/**
 * Plugin Name: Kovacic Language Switcher
 * Description: Adds a lightweight language switcher to individual pages and lets editors provide a translated HTML version of the content.
 * Version: 1.0.0
 * Author: Kovacic Talent
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kovacic_Language_Switcher {
    private const META_KEY = '_kls_translations';

    public function __construct() {
        add_action('init', [$this, 'register_assets']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post_page', [$this, 'save_meta_box']);
        add_filter('the_content', [$this, 'inject_language_switcher'], 20);
    }

    public function register_assets(): void {
        $version = '1.0.0';
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

    public function render_meta_box(\WP_Post $post): void {
        $saved = get_post_meta($post->ID, self::META_KEY, true);
        $defaults = [
            'enabled' => !empty($saved['spanish_content']) || !empty($saved['enabled']),
            'default_language' => $saved['default_language'] ?? 'en',
            'english_label' => $saved['english_label'] ?? __('English', 'kovacic-language-switcher'),
            'spanish_label' => $saved['spanish_label'] ?? __('Español', 'kovacic-language-switcher'),
            'spanish_content' => $saved['spanish_content'] ?? '',
        ];

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

        $enabled = !empty($raw['enabled']);
        $data = [
            'enabled' => $enabled,
            'default_language' => in_array($raw['default_language'] ?? 'en', ['en', 'es'], true) ? $raw['default_language'] : 'en',
            'english_label' => sanitize_text_field($raw['english_label'] ?? __('English', 'kovacic-language-switcher')),
            'spanish_label' => sanitize_text_field($raw['spanish_label'] ?? __('Español', 'kovacic-language-switcher')),
            'spanish_content' => $this->prepare_spanish_content($raw['spanish_content'] ?? ''),
        ];

        if (!$enabled) {
            $data['spanish_content'] = '';
        }

        if (!$data['enabled'] || empty($data['spanish_content'])) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        update_post_meta($post_id, self::META_KEY, $data);
    }

    private function prepare_spanish_content(string $content): string {
        $content = wp_unslash($content);

        if (current_user_can('unfiltered_html')) {
            return $content;
        }

        return wp_kses_post($content);
    }

    public function inject_language_switcher(string $content): string {
        if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        $settings = get_post_meta($post_id, self::META_KEY, true);

        if (empty($settings) || empty($settings['spanish_content'])) {
            return $content;
        }

        wp_enqueue_style('kls-switcher');
        wp_enqueue_script('kls-switcher');

        $english_label = $settings['english_label'] ?? __('English', 'kovacic-language-switcher');
        $spanish_label = $settings['spanish_label'] ?? __('Español', 'kovacic-language-switcher');
        $default = $settings['default_language'] ?? 'en';
        $default = in_array($default, ['en', 'es'], true) ? $default : 'en';

        $english_active = $default === 'en' ? ' is-active' : '';
        $spanish_active = $default === 'es' ? ' is-active' : '';

        ob_start();
        ?>
        <div class="kls-switcher" data-default="<?php echo esc_attr($default); ?>">
            <div class="kls-switcher__buttons" role="tablist" aria-label="<?php echo esc_attr__('Language selector', 'kovacic-language-switcher'); ?>">
                <button type="button" class="kls-switcher__button<?php echo esc_attr($english_active); ?>" data-lang="en" role="tab" aria-selected="<?php echo $default === 'en' ? 'true' : 'false'; ?>" aria-controls="kls-lang-en">
                    <?php echo esc_html($english_label); ?>
                </button>
                <button type="button" class="kls-switcher__button<?php echo esc_attr($spanish_active); ?>" data-lang="es" role="tab" aria-selected="<?php echo $default === 'es' ? 'true' : 'false'; ?>" aria-controls="kls-lang-es">
                    <?php echo esc_html($spanish_label); ?>
                </button>
            </div>
            <div class="kls-switcher__panels">
                <div id="kls-lang-en" class="kls-switcher__panel<?php echo esc_attr($english_active); ?>" role="tabpanel" data-lang="en">
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div id="kls-lang-es" class="kls-switcher__panel<?php echo esc_attr($spanish_active); ?>" role="tabpanel" data-lang="es">
                    <?php echo $settings['spanish_content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Kovacic_Language_Switcher();

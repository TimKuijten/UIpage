<?php
/**
 * Plugin Name: Kovacic Language Switcher
 * Description: Adds a lightweight language switcher to individual pages and lets editors provide translations for on-page strings.
 * Version: 2.1.2
 * Author: Kovacic Talent
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kovacic_Language_Switcher {
    private const META_KEY = '_kls_string_translations';

    private ?array $current_settings = null;
    private bool $portal_rendered = false;
    private bool $nav_switcher_added = false;

    public function __construct() {
        add_action('init', [$this, 'register_assets']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post_page', [$this, 'save_meta_box']);
        add_action('template_redirect', [$this, 'prepare_frontend']);
        add_filter('wp_nav_menu_items', [$this, 'inject_into_nav_menu'], 10, 2);
    }

    public function register_assets(): void {
        $version = '2.1.2';
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
        if (!is_array($saved)) {
            return [];
        }

        $strings = [];
        if (!empty($saved['strings']) && is_array($saved['strings'])) {
            foreach ($saved['strings'] as $key => $value) {
                $normalized = $this->normalize_string((string) $key);
                if ($normalized === '') {
                    continue;
                }

                $strings[$normalized] = sanitize_textarea_field($value);
            }
        }

        return [
            'enabled' => !empty($saved['enabled']),
            'default_language' => in_array($saved['default_language'] ?? 'en', ['en', 'es'], true) ? $saved['default_language'] : 'en',
            'english_label' => $saved['english_label'] ?? 'EN',
            'spanish_label' => $saved['spanish_label'] ?? 'ES',
            'strings' => $strings,
        ];
    }

    private function build_defaults(array $settings = []): array {
        return [
            'enabled' => !empty($settings['enabled']),
            'default_language' => $settings['default_language'] ?? 'en',
            'english_label' => $settings['english_label'] ?? 'EN',
            'spanish_label' => $settings['spanish_label'] ?? 'ES',
            'strings' => is_array($settings['strings'] ?? null) ? $settings['strings'] : [],
        ];
    }

    public function render_meta_box(\WP_Post $post): void {
        $defaults = $this->build_defaults($this->get_page_settings($post->ID));
        $strings = $this->collect_page_strings($post);

        wp_nonce_field('kls_translation_box', 'kls_translation_nonce');
        ?>
        <p><?php esc_html_e('Provide the Spanish translation for the visible text on this page. The original page content is treated as English.', 'kovacic-language-switcher'); ?></p>
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
        <h2><?php esc_html_e('Translations', 'kovacic-language-switcher'); ?></h2>
        <?php if (empty($strings)) : ?>
            <p class="description"><?php esc_html_e('No strings were detected for this page. Publish the page content first, then revisit this screen.', 'kovacic-language-switcher'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Original text', 'kovacic-language-switcher'); ?></th>
                        <th scope="col"><?php esc_html_e('Spanish translation', 'kovacic-language-switcher'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($strings as $string) :
                    $hash = md5($string['normalized']);
                    $saved_value = $defaults['strings'][$string['normalized']] ?? '';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($string['display']); ?></strong>
                            <?php if ($string['display'] !== $string['original']) : ?>
                                <p class="description"><?php echo esc_html($string['original']); ?></p>
                            <?php endif; ?>
                            <input type="hidden" name="kls_switcher[string_map][<?php echo esc_attr($hash); ?>]" value="<?php echo esc_attr($string['normalized']); ?>" />
                        </td>
                        <td>
                            <textarea name="kls_switcher[strings][<?php echo esc_attr($hash); ?>]" rows="2" class="widefat" placeholder="<?php esc_attr_e('Enter Spanish translation', 'kovacic-language-switcher'); ?>"><?php echo esc_textarea($saved_value); ?></textarea>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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

    private function persist_page_settings(int $post_id, array $raw): void {
        $raw = wp_unslash($raw);

        $enabled = !empty($raw['enabled']);
        $default_language = in_array($raw['default_language'] ?? 'en', ['en', 'es'], true) ? $raw['default_language'] : 'en';

        $strings = [];
        $string_map = $raw['string_map'] ?? [];
        $translations = $raw['strings'] ?? [];

        if (is_array($string_map) && is_array($translations)) {
            foreach ($string_map as $hash => $original) {
                $normalized = $this->normalize_string((string) $original);
                if ($normalized === '' || !isset($translations[$hash])) {
                    continue;
                }

                $translation = sanitize_textarea_field($translations[$hash]);
                if ($translation === '') {
                    continue;
                }

                $strings[$normalized] = $translation;
            }
        }

        $data = [
            'enabled' => $enabled,
            'default_language' => $default_language,
            'english_label' => sanitize_text_field($raw['english_label'] ?? 'EN'),
            'spanish_label' => sanitize_text_field($raw['spanish_label'] ?? 'ES'),
            'strings' => $strings,
        ];

        if (!$enabled && empty($strings)) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        update_post_meta($post_id, self::META_KEY, $data);
    }

    public function prepare_frontend(): void {
        if (!is_singular('page') || isset($_GET['kls_admin_preview'])) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $settings = $this->get_page_settings($post_id);
        if (empty($settings) || empty($settings['enabled']) || empty($settings['strings'])) {
            return;
        }

        $this->current_settings = $settings;
        $this->nav_switcher_added = false;
        $this->portal_rendered = false;

        wp_enqueue_style('kls-switcher');
        wp_enqueue_script('kls-switcher');

        wp_localize_script('kls-switcher', 'KLSData', [
            'defaultLanguage' => $this->current_settings['default_language'] ?? 'en',
            'englishLabel' => $this->current_settings['english_label'] ?? __('English', 'kovacic-language-switcher'),
            'spanishLabel' => $this->current_settings['spanish_label'] ?? __('Español', 'kovacic-language-switcher'),
            'htmlLang' => get_bloginfo('language') ?: 'en',
            'translations' => $this->current_settings['strings'],
        ]);

        add_action('wp_body_open', [$this, 'render_switcher_portal'], 5);
    }

    public function render_switcher_portal(): void {
        if ($this->portal_rendered || empty($this->current_settings) || $this->nav_switcher_added) {
            return;
        }

        $this->portal_rendered = true;
        ?>
        <div id="kls-switcher-root" class="kls-switcher-portal" hidden>
            <?php echo $this->get_switcher_markup(false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    public function inject_into_nav_menu(string $items, $args): string {
        if (is_admin() || empty($this->current_settings) || empty($this->current_settings['enabled']) || empty($this->current_settings['strings'])) {
            return $items;
        }

        $theme_location = $args->theme_location ?? '';
        if ($theme_location) {
            if (strtolower((string) $theme_location) !== 'primary') {
                return $items;
            }
        } else {
            $menu_id = strtolower((string) ($args->menu_id ?? ''));
            $menu_class = strtolower((string) ($args->menu_class ?? ''));
            $identifier = trim($menu_id . ' ' . $menu_class);

            if ($identifier !== '' && strpos($identifier, 'primary') === false) {
                return $items;
            }
        }

        if ($this->nav_switcher_added) {
            return $items;
        }

        $markup = $this->get_nav_item_markup();
        if ($markup === '') {
            return $items;
        }

        $pattern = '/(<li[^>]*><a[^>]*href="[^"]*linkedin\.com[^"]*"[^>]*>.*?<\/a>.*?<\/li>)/i';
        if (preg_match($pattern, $items, $matches)) {
            $items = str_replace($matches[0], $markup . $matches[0], $items);
        } else {
            $items .= $markup;
        }

        $this->nav_switcher_added = true;

        return $items;
    }

    private function get_nav_item_markup(): string {
        $switcher = $this->get_switcher_markup(true);
        if ($switcher === '') {
            return '';
        }

        $classes = apply_filters('kls_nav_item_classes', ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'kls-switcher__item']);
        $class_attribute = esc_attr(implode(' ', array_unique(array_filter(array_map('sanitize_html_class', $classes)))));

        return sprintf('<li class="%s">%s</li>', $class_attribute, $switcher);
    }

    private function get_switcher_markup(bool $is_nav): string {
        if (empty($this->current_settings)) {
            return '';
        }

        $default = $this->current_settings['default_language'] ?? 'en';
        $english_active = $default === 'en';
        $spanish_active = $default === 'es';

        $english_setting = $this->current_settings['english_label'] ?? 'EN';
        $spanish_setting = $this->current_settings['spanish_label'] ?? 'ES';
        $english_label = esc_html($this->get_switcher_label($english_setting, 'EN', 'en'));
        $spanish_label = esc_html($this->get_switcher_label($spanish_setting, 'ES', 'es'));
        $group_label = __('Language selector', 'kovacic-language-switcher');

        ob_start();
        ?>
        <div class="kls-switcher<?php echo $is_nav ? ' kls-switcher--nav' : ''; ?>" role="group" aria-label="<?php echo esc_attr($group_label); ?>">
            <button type="button" class="kls-switcher__button<?php echo $english_active ? ' kls-switcher__button--active' : ''; ?>" data-language="en" aria-pressed="<?php echo $english_active ? 'true' : 'false'; ?>"><?php echo $english_label; ?></button>
            <button type="button" class="kls-switcher__button<?php echo $spanish_active ? ' kls-switcher__button--active' : ''; ?>" data-language="es" aria-pressed="<?php echo $spanish_active ? 'true' : 'false'; ?>"><?php echo $spanish_label; ?></button>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    private function get_switcher_label($value, string $fallback, string $language_code): string {
        $label = is_string($value) ? trim($value) : '';
        if ($label === '') {
            return $fallback;
        }

        if (function_exists('mb_strtoupper')) {
            $label = mb_strtoupper($label, 'UTF-8');
        } else {
            $label = strtoupper($label);
        }

        $length = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
        if ($length > 3) {
            $label = function_exists('mb_strtoupper') ? mb_strtoupper($language_code, 'UTF-8') : strtoupper($language_code);
        }

        return $label === '' ? $fallback : $label;
    }

    private function collect_page_strings(\WP_Post $post): array {
        $content = $this->fetch_frontend_html($post);
        if ($content === '') {
            $content = $this->render_post_content($post);
        }
        if ($content === '') {
            return [];
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $html = function_exists('mb_convert_encoding') ? mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8') : $content;
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//text()[normalize-space()]');

        if (!$nodes || $nodes->length === 0) {
            return [];
        }

        $strings = [];

        /** @var \DOMText $node */
        foreach ($nodes as $node) {
            $parent = $node->parentNode;
            if (!$parent instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($parent->nodeName);
            if (in_array($tag, ['script', 'style', 'noscript', 'template'], true)) {
                continue;
            }

            $original = $node->nodeValue ?? '';
            $normalized = $this->normalize_string($original);
            if ($normalized === '') {
                continue;
            }

            if (isset($strings[$normalized])) {
                continue;
            }

            $display = trim(preg_replace('/\s+/u', ' ', $original));
            if ($display === '') {
                $display = $normalized;
            }

            $strings[$normalized] = [
                'normalized' => $normalized,
                'original' => $original,
                'display' => $display,
            ];
        }

        return array_values($strings);
    }

    private function fetch_frontend_html(\WP_Post $post): string {
        $permalink = get_permalink($post);
        if (!is_string($permalink) || $permalink === '') {
            return '';
        }

        $url = add_query_arg('kls_admin_preview', '1', $permalink);
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'redirection' => 3,
            'headers' => [
                'Cache-Control' => 'no-cache',
            ],
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        if ((int) $code !== 200) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || $body === '') {
            return '';
        }

        return $body;
    }

    private function render_post_content(\WP_Post $post): string {
        $content = $post->post_content;
        if (!is_string($content) || $content === '') {
            return '';
        }

        $rendered = apply_filters('the_content', $content);
        if (!is_string($rendered)) {
            return '';
        }

        return $rendered;
    }

    private function normalize_string(string $value): string {
        $charset = get_bloginfo('charset');
        if (!is_string($charset) || $charset === '') {
            $charset = 'UTF-8';
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $charset);
        $collapsed = preg_replace('/\s+/u', ' ', (string) $decoded);

        return trim((string) $collapsed);
    }
}

new Kovacic_Language_Switcher();

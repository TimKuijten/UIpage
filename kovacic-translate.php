<?php
/**
 * Plugin Name: Kovacic Translate
 * Description: Manual translation manager for Kovacic pages with per-string controls and a language switcher.
 * Version: 1.0.0
 * Author: Kovacic Executive Talent Research
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kovacic_Translate_Plugin {
    const OPTION_LANGUAGES = 'ktl_languages';
    const META_TRANSLATIONS = '_ktl_translations';
    const QUERY_VAR = 'ktlang';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_ktl_save_languages', [$this, 'handle_save_languages']);
        add_action('admin_post_ktl_save_translations', [$this, 'handle_save_translations']);
        add_action('init', [$this, 'maybe_set_language_cookie']);
        add_filter('the_content', [$this, 'filter_content'], 20);
        add_filter('the_title', [$this, 'filter_title'], 20, 2);
        add_filter('wp_nav_menu_items', [$this, 'inject_language_switcher'], 20, 2);
        add_action('wp_head', [$this, 'print_frontend_styles']);
        add_action('wp_footer', [$this, 'print_frontend_script']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Kovacic Translate', 'kovacic-translate'),
            __('Kovacic Translate', 'kovacic-translate'),
            'manage_options',
            'ktl_translations',
            [$this, 'render_admin_page'],
            'dashicons-translation',
            58
        );
    }

    public function plugin_action_links($links) {
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=ktl_translations')) . '">' . esc_html__('Settings', 'kovacic-translate') . '</a>';
        return $links;
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $post_id = isset($_GET['edit_page']) ? absint($_GET['edit_page']) : 0;

        echo '<div class="wrap ktl-wrap">';
        echo '<h1>' . esc_html__('Kovacic Translate', 'kovacic-translate') . '</h1>';
        echo '<style>
            .ktl-wrap .ktl-language-form textarea{max-width:520px;}
            .ktl-wrap .ktl-language-picker{margin:16px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
            .ktl-wrap .ktl-strings-grid{display:grid;gap:16px;margin-top:18px;}
            .ktl-wrap .ktl-string{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);}
            .ktl-wrap .ktl-string-original{font-weight:600;margin-bottom:8px;color:#1d2327;}
            .ktl-wrap .ktl-string textarea{width:100%;resize:vertical;}
            .ktl-wrap .ktl-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;}
            .ktl-wrap .ktl-page-table td{vertical-align:middle;}
        </style>';

        if ($post_id) {
            $this->render_page_editor($post_id);
        } else {
            $this->render_overview();
        }

        echo '</div>';
    }

    private function render_overview() {
        $settings = $this->get_language_settings();
        $default_lang = $settings['default'];
        $languages = $settings['languages'];
        $textarea_value = '';
        foreach ($languages as $code => $label) {
            $textarea_value .= $code . ' | ' . $label . "\n";
        }
        $textarea_value = trim($textarea_value);

        if (!empty($_GET['languages-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Language settings saved.', 'kovacic-translate') . '</p></div>';
        }
        if (!empty($_GET['translations-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Translations saved.', 'kovacic-translate') . '</p></div>';
        }

        echo '<h2>' . esc_html__('Languages', 'kovacic-translate') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ktl-language-form">';
        wp_nonce_field('ktl_save_languages');
        echo '<input type="hidden" name="action" value="ktl_save_languages">';
        echo '<table class="form-table"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="ktl-default-language">' . esc_html__('Default language code', 'kovacic-translate') . '</label></th>';
        echo '<td><input type="text" id="ktl-default-language" name="ktl_default_language" value="' . esc_attr($default_lang) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Two-letter code that represents the source language of your content.', 'kovacic-translate') . '</p></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="ktl-languages">' . esc_html__('Languages list', 'kovacic-translate') . '</label></th>';
        echo '<td>';
        echo '<textarea id="ktl-languages" name="ktl_languages" class="large-text code" rows="5" placeholder="en | English&#10;es | Español">' . esc_textarea($textarea_value) . '</textarea>';
        echo '<p class="description">' . esc_html__('One language per line using the format code | Label. Include the default language and all translations you want to offer.', 'kovacic-translate') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';
        submit_button(__('Save languages', 'kovacic-translate'));
        echo '</form>';

        echo '<hr class="ktl-divider">';
        echo '<h2>' . esc_html__('Pages', 'kovacic-translate') . '</h2>';

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (!$pages) {
            echo '<p>' . esc_html__('No published pages found.', 'kovacic-translate') . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped ktl-page-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Title', 'kovacic-translate') . '</th>';
        echo '<th>' . esc_html__('Status', 'kovacic-translate') . '</th>';
        echo '<th>' . esc_html__('Updated', 'kovacic-translate') . '</th>';
        echo '<th>' . esc_html__('Actions', 'kovacic-translate') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($pages as $page) {
            $edit_link = add_query_arg([
                'page' => 'ktl_translations',
                'edit_page' => $page->ID,
            ], admin_url('admin.php'));
            echo '<tr>';
            echo '<td>' . esc_html(get_the_title($page)) . '</td>';
            echo '<td>' . esc_html(ucfirst($page->post_status)) . '</td>';
            echo '<td>' . esc_html(get_the_modified_date('', $page)) . '</td>';
            echo '<td><a class="button" href="' . esc_url($edit_link) . '">' . esc_html__('Edit translations', 'kovacic-translate') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_page_editor($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') {
            echo '<p>' . esc_html__('Invalid page.', 'kovacic-translate') . '</p>';
            return;
        }

        $settings = $this->get_language_settings();
        $default_lang = $settings['default'];
        $languages = $settings['languages'];
        $translation_languages = array_diff_key($languages, [$default_lang => true]);

        if (!$translation_languages) {
            echo '<p>' . esc_html__('Add at least one translation language to start translating this page.', 'kovacic-translate') . '</p>';
            return;
        }

        $current_lang = isset($_GET['lang']) ? sanitize_key($_GET['lang']) : '';
        if (!$current_lang || !isset($translation_languages[$current_lang])) {
            $current_lang = key($translation_languages);
        }

        $strings = $this->extract_strings($post->post_content);
        $data = $this->get_translation_data($post_id);
        $data['strings'] = $strings;
        $translations = isset($data['translations'][$current_lang]) ? $data['translations'][$current_lang] : [];

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=ktl_translations')) . '" class="ktl-back">&larr; ' . esc_html__('Back to all pages', 'kovacic-translate') . '</a></p>';
        echo '<h2>' . esc_html(sprintf(__('Editing “%s”', 'kovacic-translate'), get_the_title($post))) . '</h2>';
        echo '<p class="description">' . esc_html__('Provide translations for the strings detected in this page. Empty fields fall back to the original text.', 'kovacic-translate') . '</p>';

        echo '<form method="get" action="" class="ktl-language-picker">';
        echo '<input type="hidden" name="page" value="ktl_translations">';
        echo '<input type="hidden" name="edit_page" value="' . esc_attr($post_id) . '">';
        echo '<label for="ktl-lang-select">' . esc_html__('Language:', 'kovacic-translate') . '</label> ';
        echo '<select id="ktl-lang-select" name="lang">';
        foreach ($translation_languages as $code => $label) {
            echo '<option value="' . esc_attr($code) . '"' . selected($code, $current_lang, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        submit_button(__('Switch', 'kovacic-translate'), 'secondary', '', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ktl-strings-form">';
        wp_nonce_field('ktl_save_translations_' . $post_id . '_' . $current_lang);
        echo '<input type="hidden" name="action" value="ktl_save_translations">';
        echo '<input type="hidden" name="ktl_post_id" value="' . esc_attr($post_id) . '">';
        echo '<input type="hidden" name="ktl_language" value="' . esc_attr($current_lang) . '">';

        if (!$strings) {
            echo '<p>' . esc_html__('No translatable strings were detected in this page.', 'kovacic-translate') . '</p>';
        } else {
            echo '<div class="ktl-strings-grid">';
            foreach ($strings as $key => $string) {
                $original = $string['normalized'];
                $raw = isset($string['original']) ? $string['original'] : $original;
                $value = isset($translations[$key]) ? $translations[$key] : '';
                echo '<div class="ktl-string">';
                echo '<div class="ktl-string-original">' . esc_html($raw) . '</div>';
                echo '<textarea name="ktl_translations[' . esc_attr($key) . ']" rows="2" placeholder="' . esc_attr__('Translation…', 'kovacic-translate') . '">' . esc_textarea($value) . '</textarea>';
                echo '</div>';
            }
            echo '</div>';
        }

        submit_button(__('Save translations', 'kovacic-translate'));
        echo '</form>';
    }

    public function handle_save_languages() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'kovacic-translate'));
        }
        check_admin_referer('ktl_save_languages');

        $default = isset($_POST['ktl_default_language']) ? sanitize_key(wp_unslash($_POST['ktl_default_language'])) : '';
        $raw_list = isset($_POST['ktl_languages']) ? wp_unslash($_POST['ktl_languages']) : '';
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $raw_list)));

        $languages = [];
        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            if (strpos($line, '|') !== false) {
                list($code, $label) = array_map('trim', explode('|', $line, 2));
            } else {
                $parts = preg_split('/\s+/', $line, 2);
                $code = trim($parts[0]);
                $label = isset($parts[1]) ? trim($parts[1]) : strtoupper($code);
            }
            $code = sanitize_key($code);
            if (!$code) {
                continue;
            }
            $languages[$code] = $label ? sanitize_text_field($label) : strtoupper($code);
        }

        if (!$default || !isset($languages[$default])) {
            $default = key($languages);
        }
        if (!$default) {
            $default = $this->infer_default_language();
            $languages[$default] = isset($languages[$default]) ? $languages[$default] : $this->label_from_code($default);
        }

        $settings = [
            'default' => $default,
            'languages' => $languages,
        ];
        update_option(self::OPTION_LANGUAGES, $settings);

        $redirect = add_query_arg('languages-updated', '1', admin_url('admin.php?page=ktl_translations'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_save_translations() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'kovacic-translate'));
        }

        $post_id = isset($_POST['ktl_post_id']) ? absint($_POST['ktl_post_id']) : 0;
        $language = isset($_POST['ktl_language']) ? sanitize_key(wp_unslash($_POST['ktl_language'])) : '';

        if (!$post_id || !$language) {
            wp_die(__('Missing required data.', 'kovacic-translate'));
        }

        check_admin_referer('ktl_save_translations_' . $post_id . '_' . $language);

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') {
            wp_die(__('Invalid page.', 'kovacic-translate'));
        }

        $settings = $this->get_language_settings();
        $default_lang = $settings['default'];
        if ($language === $default_lang) {
            wp_die(__('You cannot override the default language.', 'kovacic-translate'));
        }

        $languages = $settings['languages'];
        if (!isset($languages[$language])) {
            wp_die(__('Unknown language.', 'kovacic-translate'));
        }

        $strings = $this->extract_strings($post->post_content);
        $data = $this->get_translation_data($post_id);
        $data['strings'] = $strings;

        if (!empty($data['translations'])) {
            foreach ($data['translations'] as $lang_code => $lang_translations) {
                if (!is_array($lang_translations)) {
                    $data['translations'][$lang_code] = [];
                    continue;
                }
                if ($lang_code === $language) {
                    continue;
                }
                $data['translations'][$lang_code] = array_intersect_key($lang_translations, $strings);
            }
        }

        $input = isset($_POST['ktl_translations']) && is_array($_POST['ktl_translations']) ? $_POST['ktl_translations'] : [];
        $translations = [];
        foreach ($strings as $key => $info) {
            $value = isset($input[$key]) ? wp_kses_post(wp_unslash($input[$key])) : '';
            if ($value !== '') {
                $translations[$key] = $value;
            }
        }

        $data['translations'][$language] = $translations;
        $data['updated'] = time();

        update_post_meta($post_id, self::META_TRANSLATIONS, $data);

        $redirect = add_query_arg([
            'page' => 'ktl_translations',
            'edit_page' => $post_id,
            'lang' => $language,
            'translations-updated' => '1',
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function maybe_set_language_cookie() {
        if (is_admin()) {
            return;
        }
        if (!isset($_GET[self::QUERY_VAR])) {
            return;
        }
        $requested = sanitize_key(wp_unslash($_GET[self::QUERY_VAR]));
        $languages = $this->get_languages();
        if (!isset($languages[$requested])) {
            return;
        }
        setcookie(self::QUERY_VAR, $requested, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::QUERY_VAR] = $requested;
    }

    public function filter_content($content) {
        if (is_admin()) {
            return $content;
        }
        if (!is_singular()) {
            return $content;
        }
        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }
        $lang = $this->get_current_language();
        if ($lang === $this->get_default_language()) {
            return $content;
        }
        $map = $this->get_translation_map($post_id, $lang);
        if (!$map) {
            return $content;
        }
        return $this->replace_strings_in_html($content, $map);
    }

    public function filter_title($title, $post_id) {
        if (is_admin() || !is_singular() || get_the_ID() !== $post_id) {
            return $title;
        }
        $lang = $this->get_current_language();
        if ($lang === $this->get_default_language()) {
            return $title;
        }
        $map = $this->get_translation_map($post_id, $lang);
        if (!$map) {
            return $title;
        }
        $normalized = $this->normalize_string($title);
        if ($normalized && isset($map[$normalized])) {
            return $map[$normalized];
        }
        return $title;
    }

    public function inject_language_switcher($items, $args) {
        if (empty($args->theme_location) || 'primary-menu' !== $args->theme_location) {
            return $items;
        }
        $languages = $this->get_languages();
        if (count($languages) < 2) {
            return $items;
        }
        $current = $this->get_current_language();
        $links = '';
        foreach ($languages as $code => $label) {
            $url = $this->language_url($code);
            $class = 'ktl-language-item';
            if ($code === $current) {
                $class .= ' is-active';
            }
            $links .= '<li class="menu-item ktl-language-switcher-item ' . esc_attr($class) . '"><a href="' . esc_url($url) . '"' . ($code === $current ? ' aria-current="true"' : '') . '>' . esc_html($this->language_short_label($code, $label)) . '</a></li>';
        }
        return $items . $links;
    }

    public function print_frontend_script() {
        if (is_admin() || !is_singular()) {
            return;
        }
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        $lang = $this->get_current_language();
        if ($lang === $this->get_default_language()) {
            return;
        }
        $map = $this->get_translation_map($post_id, $lang);
        if (!$map) {
            return;
        }
        $json = wp_json_encode($map);
        if (!$json) {
            return;
        }
        echo "<script id='ktl-translations'>\n";
        echo "(function(){\n";
        echo "  var map = " . $json . ";\n";
        echo "  if(!map) return;\n";
        echo "  var normalize = function(text){ return text ? text.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim() : ''; };\n";
        echo "  var applyToNode = function(node){ if(node.nodeType !== Node.TEXT_NODE) return; var value = node.textContent; var key = normalize(value); if(!key) return; var translation = map[key]; if(!translation) return; var leading = value.match(/^\s*/); var trailing = value.match(/\s*$/); node.textContent = (leading ? leading[0] : '') + translation + (trailing ? trailing[0] : ''); };\n";
        echo "  var applyTree = function(root){ if(!root) return; var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null); var current; while((current = walker.nextNode())){ applyToNode(current); } };\n";
        echo "  var observer = new MutationObserver(function(mutations){ mutations.forEach(function(m){ if(m.type === 'characterData'){ applyToNode(m.target); } else { m.addedNodes.forEach(function(node){ if(node.nodeType === Node.TEXT_NODE){ applyToNode(node); } else if(node.nodeType === Node.ELEMENT_NODE){ applyTree(node); } }); } }); });\n";
        echo "  var start = function(){ applyTree(document.body); observer.observe(document.body, {childList:true, subtree:true, characterData:true}); };\n";
        echo "  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', start); } else { start(); }\n";
        echo "})();\n";
        echo "</script>\n";
    }

    private function get_languages() {
        $settings = $this->get_language_settings();
        return $settings['languages'];
    }

    private function get_default_language() {
        $settings = $this->get_language_settings();
        return $settings['default'];
    }

    private function get_current_language() {
        $languages = $this->get_languages();
        $default = $this->get_default_language();
        $selected = isset($_COOKIE[self::QUERY_VAR]) ? sanitize_key($_COOKIE[self::QUERY_VAR]) : '';
        if (!$selected || !isset($languages[$selected])) {
            $selected = $default;
        }
        return $selected;
    }

    private function language_url($code) {
        $url = remove_query_arg(self::QUERY_VAR);
        return add_query_arg(self::QUERY_VAR, $code, $url);
    }

    private function language_short_label($code, $label) {
        $parts = preg_split('/[\s\-]/', $label);
        if (!$parts) {
            return strtoupper($code);
        }
        $first = reset($parts);
        if (strlen($first) <= 4) {
            return $first;
        }
        return strtoupper($code);
    }

    private function get_language_settings() {
        $settings = get_option(self::OPTION_LANGUAGES, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $defaults = [
            'default' => $this->infer_default_language(),
            'languages' => [
                'en' => 'English',
                'es' => 'Español',
            ],
        ];
        $settings = wp_parse_args($settings, $defaults);
        if (!is_array($settings['languages'])) {
            $settings['languages'] = $defaults['languages'];
        }
        $clean = [];
        foreach ($settings['languages'] as $code => $label) {
            $code = sanitize_key($code);
            if (!$code) {
                continue;
            }
            $clean[$code] = $label ? sanitize_text_field($label) : strtoupper($code);
        }
        if (!$clean) {
            $clean = $defaults['languages'];
        }
        if (empty($clean[$settings['default']])) {
            $settings['default'] = key($clean);
        }
        $settings['languages'] = $clean;
        return $settings;
    }

    public function print_frontend_styles() {
        if (is_admin()) {
            return;
        }
        $languages = $this->get_languages();
        if (count($languages) < 2) {
            return;
        }
        echo '<style id="ktl-language-switcher-styles">.ktl-language-switcher-item{list-style:none;margin-left:0;}
.ktl-language-switcher-item a{display:inline-flex;align-items:center;gap:6px;padding:0.45rem 0.7rem;border-radius:999px;border:1px solid rgba(16,24,40,.18);font-weight:600;font-size:0.9rem;line-height:1;color:inherit;transition:all .2s ease;}
.ktl-language-switcher-item a:hover{background:rgba(10,33,46,.08);text-decoration:none;}
.ktl-language-switcher-item.is-active a{background:#0A212E;color:#fff;border-color:#0A212E;}
</style>';
    }

    private function infer_default_language() {
        $locale = get_locale();
        if (!$locale) {
            return 'en';
        }
        $code = strtolower(substr($locale, 0, 2));
        return $code ? $code : 'en';
    }

    private function label_from_code($code) {
        $code = strtolower($code);
        $map = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
        ];
        return isset($map[$code]) ? $map[$code] : strtoupper($code);
    }

    private function get_translation_data($post_id) {
        $data = get_post_meta($post_id, self::META_TRANSLATIONS, true);
        if (!is_array($data)) {
            $data = [];
        }
        if (!isset($data['strings']) || !is_array($data['strings'])) {
            $data['strings'] = [];
        }
        if (!isset($data['translations']) || !is_array($data['translations'])) {
            $data['translations'] = [];
        }
        return $data;
    }

    private function get_translation_map($post_id, $language) {
        $data = $this->get_translation_data($post_id);
        if (empty($data['translations'][$language])) {
            return [];
        }
        $map = [];
        $strings = $data['strings'];
        if (!$strings) {
            $strings = $this->extract_strings(get_post_field('post_content', $post_id));
        }
        foreach ($data['translations'][$language] as $key => $translation) {
            if (isset($strings[$key])) {
                $normalized = isset($strings[$key]['normalized']) ? $strings[$key]['normalized'] : $this->normalize_string($strings[$key]['original']);
                if ($normalized) {
                    $map[$normalized] = $translation;
                }
            }
        }
        return $map;
    }

    private function extract_strings($html) {
        $strings = [];
        if (!$html) {
            return $strings;
        }
        $internal = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="utf-8" ?>' . $html;
        if (!$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($internal);
            return $strings;
        }
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//text()[normalize-space()]');
        if ($nodes) {
            foreach ($nodes as $node) {
                $parent = $node->parentNode;
                if ($parent) {
                    $tag = strtolower($parent->nodeName);
                    if (in_array($tag, ['script', 'style', 'noscript'])) {
                        continue;
                    }
                }
                $value = $node->nodeValue;
                $normalized = $this->normalize_string($value);
                if ($normalized === '') {
                    continue;
                }
                $key = sha1($normalized);
                if (!isset($strings[$key])) {
                    $strings[$key] = [
                        'original' => $value,
                        'normalized' => $normalized,
                    ];
                }
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($internal);
        return $strings;
    }

    private function normalize_string($value) {
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value));
        return $value;
    }

    private function replace_strings_in_html($html, $map) {
        $internal = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="utf-8" ?>' . $html;
        if (!$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($internal);
            return $html;
        }
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//text()[normalize-space()]');
        if ($nodes) {
            foreach ($nodes as $node) {
                $parent = $node->parentNode;
                if ($parent) {
                    $tag = strtolower($parent->nodeName);
                    if (in_array($tag, ['script', 'style', 'noscript'])) {
                        continue;
                    }
                }
                $original_value = $node->nodeValue;
                $normalized = $this->normalize_string($original_value);
                if ($normalized === '' || !isset($map[$normalized])) {
                    continue;
                }
                $translation = $map[$normalized];
                $leading = $this->leading_whitespace($original_value);
                $trailing = $this->trailing_whitespace($original_value);
                $node->nodeValue = $leading . $translation . $trailing;
            }
        }
        $result = $doc->saveHTML();
        libxml_clear_errors();
        libxml_use_internal_errors($internal);
        return $result;
    }

    private function leading_whitespace($value) {
        if (preg_match('/^\s+/u', $value, $matches)) {
            return $matches[0];
        }
        return '';
    }

    private function trailing_whitespace($value) {
        if (preg_match('/\s+$/u', $value, $matches)) {
            return $matches[0];
        }
        return '';
    }
}

new Kovacic_Translate_Plugin();

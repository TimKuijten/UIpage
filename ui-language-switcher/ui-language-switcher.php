<?php
/**
 * Plugin Name: UI Language Switcher
 * Description: Provides English/Spanish toggles for Kovacic Talent static HTML pages.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class UI_Language_Switcher {
    private $pages = [
        'article_page' => ['label' => 'Article page'],
        'cv_feedback'  => ['label' => 'CV feedback page'],
        'page2'        => ['label' => 'Executive recruitment page'],
        'terms'        => ['label' => 'Privacy & terms page'],
    ];

    private $localized = false;

    public function __construct() {
        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_shortcode() {
        add_shortcode('ui_language_page', [$this, 'render_shortcode']);
    }

    public function register_assets() {
        $version = '1.0.0';
        $base    = plugin_dir_url(__FILE__);

        wp_register_style(
            'ui-language-switcher',
            $base . 'assets/css/ui-language-switcher.css',
            [],
            $version
        );

        wp_register_script(
            'ui-language-switcher',
            $base . 'assets/js/ui-language-switcher.js',
            [],
            $version,
            true
        );
    }

    private function template_map() {
        $map = [];
        foreach ($this->pages as $slug => $data) {
            $map[$slug] = [
                'en' => plugins_url('templates/en/' . $slug . '.html', __FILE__),
                'es' => plugins_url('templates/es/' . $slug . '.html', __FILE__),
            ];
        }
        return $map;
    }

    public function render_shortcode($atts = []) {
        $atts = shortcode_atts(
            [
                'slug' => '',
                'page' => '',
            ],
            $atts,
            'ui_language_page'
        );

        $slug = sanitize_key($atts['slug'] ?: $atts['page']);
        if (!$slug || !isset($this->pages[$slug])) {
            return '';
        }

        wp_enqueue_style('ui-language-switcher');
        wp_enqueue_script('ui-language-switcher');

        if (!$this->localized) {
            wp_localize_script(
                'ui-language-switcher',
                'UILangSwitcherData',
                [
                    'storageKey' => 'ui_language_preference',
                    'queryParam' => 'lang',
                    'pages'      => $this->template_map(),
                ]
            );
            $this->localized = true;
        }

        $label         = $this->pages[$slug]['label'];
        $query_lang    = isset($_GET['lang']) ? strtolower(sanitize_text_field(wp_unslash($_GET['lang']))) : '';
        $default_lang  = in_array($query_lang, ['en', 'es'], true) ? $query_lang : 'en';
        $iframe_title  = sprintf('%s (%s)', $label, $default_lang === 'es' ? 'ES' : 'EN');
        $container_id  = 'ui-lang-' . uniqid('', false);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" class="ui-lang-switcher" data-page="<?php echo esc_attr($slug); ?>" data-default-lang="<?php echo esc_attr($default_lang); ?>">
            <div class="ui-lang-switcher__controls" role="group" aria-label="<?php esc_attr_e('Language selection', 'ui-language-switcher'); ?>">
                <button type="button" class="ui-lang-switcher__btn" data-lang="en" aria-pressed="false">EN</button>
                <button type="button" class="ui-lang-switcher__btn" data-lang="es" aria-pressed="false">ES</button>
            </div>
            <iframe class="ui-lang-switcher__iframe" title="<?php echo esc_attr($iframe_title); ?>" data-title-base="<?php echo esc_attr($label); ?>" src="about:blank" loading="lazy" tabindex="-1" allowfullscreen></iframe>
        </div>
        <?php
        return ob_get_clean();
    }
}

new UI_Language_Switcher();

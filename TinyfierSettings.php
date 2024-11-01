<?php

class TinyfierSettings {

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $_options;

    /**
     * Start up
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));

        $this->_options = get_option('tinyfier_settings', TinyfierWP::default_settings());
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        // This page will be under "Settings"
        add_options_page(
                __('Tinyfier settings', TINYFIER_TEXT_DOMAIN), 'Tinyfier', 'manage_options', 'tinyfier', array($this, 'create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('Tinyfier settings', TINYFIER_TEXT_DOMAIN) ?></h2>           
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields('tinyfier_settings');
                do_settings_sections('tinyfier-main-page');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
                'tinyfier_settings', // Option group
                'tinyfier_settings', // Option name
                array($this, 'sanitize') // Sanitize
        );

        $checkbox_template = '<input type="checkbox" id="%id%" name="%option%[%id%]" value="on" %checked% />';

        $settings = array(
            __('Assets minification', TINYFIER_TEXT_DOMAIN) => array(
                'minify_css' => array(
                    __('CSS', TINYFIER_TEXT_DOMAIN),
                    $checkbox_template
                ),
                'minify_js' => array(
                    __('Javascript', TINYFIER_TEXT_DOMAIN),
                    $checkbox_template
                ),
                'minify_html' => array(
                    __('HTML', TINYFIER_TEXT_DOMAIN),
                    $checkbox_template
                ),
            ),
            __('Advanced settings', TINYFIER_TEXT_DOMAIN) => array(
                'async_js' => array(
                    __('Add async attribute to the last javascript block', TINYFIER_TEXT_DOMAIN),
                    $checkbox_template
                ),
                'safe_css_order' => array(
                    __('Maintain CSS order', TINYFIER_TEXT_DOMAIN),
                    $checkbox_template
                ),
            ),
        );

        foreach ($settings as $section => $fields) {
            $section_id = str_replace('-', '_', sanitize_title($section));

            add_settings_section(
                    $section_id, // ID
                    $section, // Title
                    array($this, 'info_' . $section_id), // Callback
                    'tinyfier-main-page' // Page
            );

            foreach ($fields as $id => $params) {
                list($title, $content) = $params;

                $replaces = array(
                    '%id%' => $id,
                    '%option%' => 'tinyfier_settings',
                    '%value%' => isset($this->_options[$id]) ? esc_attr($this->_options[$id]) : '',
                    '%checked%' => isset($this->_options[$id]) && $this->_options[$id] ? 'checked="true"' : '',
                );

                add_settings_field(
                        $id, // ID
                        $title, // Title 
                        array($this, 'render_field'), // Callback
                        'tinyfier-main-page', // Page
                        $section_id, // Section      
                        array(
                    'label_for' => $id,
                    'content' => strtr($content, $replaces)
                        )
                );
            }
        }

        add_settings_field(
                'id_number', // ID
                'ID Number', // Title 
                array($this, 'id_number_callback'), // Callback
                'tinyfier-main-page', // Page
                'tinyfier_minify_section' // Section           
        );

        add_settings_field(
                'title', 'Title', array($this, 'title_callback'), 'tinyfier-main-page', 'tinyfier_minify_section'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input) {
        $new_input = array();

        foreach (TinyfierWP::default_settings() as $name => $default) {
            $new_input[$name] = array_key_exists($name, $input) ? $input[$name] : FALSE;
        }

        return $new_input;
    }

    public function info_assets_minification() {
        echo __('Reduce load time by decreasing the size and number of CSS and JS files. Automatically remove unncessary data from CSS, JS, feed, page and post HTML.', TINYFIER_TEXT_DOMAIN);
    }

    public function info_advanced_settings() {
        echo __('Advanced settings. Experiment with them if you\'re experiencing problems like Javascript errors or CSS inconsistencies.', TINYFIER_TEXT_DOMAIN);
    }

    /**
     * Render one option field
     */
    public function render_field($arg) {
        echo $arg['content'];
    }

}

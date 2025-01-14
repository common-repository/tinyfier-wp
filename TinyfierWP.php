<?php

class TinyfierWP {

    public $minify_css;
    public $minify_js;
    public $minify_html;

    /**
     * Add async attribute to the last javascript block
     * @var boolean
     */
    public $async_js;

    /**
     * Maintain CSS order (TRUE) or join all CSS even if that imply alter the order (FALSE, better performance, default)
     * @var boolean
     */
    public $safe_css_order;

    public function exec() {
        //Disable when not necessary
        if (defined('WP_ADMIN') || is_feed() || defined('DOING_AJAX') || defined('DOING_CRON') || defined('APP_REQUEST') || defined('XMLRPC_REQUEST') || defined('SHORTINIT') && SHORTINIT) {
            return FALSE;
        }

        //Load config
        $settings = get_option('tinyfier_settings', self::default_settings());
        foreach ($settings as $k => $v) {
            $this->$k = $v;
        }

        ob_start(array($this, 'ob_callback'));
    }

    public function ob_callback($buffer) {
        $replaces = array();

        $modes = array(
            'css' => $this->minify_css,
            'js' => $this->minify_js
        );

        foreach ($modes as $mode => $enabled) {
            if (!$enabled) {
                continue;
            }

            $join_queue = array();
            $assets = $this->_find_assets($buffer, $mode);
            end($assets);
            $lastk = key($assets);
            foreach ($assets as $k => $asset) {
                $replacement = $asset['original'];

                $join = $this->_suitable_for_join($asset, $mode);
                if ($join) {
                    $join_queue[] = $asset['external'];
                    $replacement = '';
                }


                $context_change = !$join;

                if ($context_change && $mode == 'css' && !$this->safe_css_order) {
                    $context_change = FALSE;
                }

                if (($context_change || $k == $lastk) && !empty($join_queue)) {
                    //Join all the previous assets
                    $url = $this->_tinyfier_url($mode, $join_queue);
                    if ($mode == 'css') {
                        $loader = '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
                    } else {
                        if ($join && $k == $lastk && $this->async_js) {
                            $loader = '<script async src="' . $url . '"></script>';
                        } else {
                            $loader = '<script src="' . $url . '"></script>';
                        }
                    }
                    $replacement = $loader . $replacement;
                    $join_queue = array();
                }

                if ($replacement != $asset['original']) {
                    $replaces[$asset['original']] = $replacement;
                }
            }
        }

        $buffer = str_replace(array_keys($replaces), array_values($replaces), $buffer);

        //Minify HTML
        if ($this->minify_html) {
            require_once dirname(__FILE__) . '/tinyfier/html/html.php';
            $buffer = Tinyfier_HTML_Tool::process($buffer, array(
                        'external_services' => FALSE
            ));
        }

        return $buffer;
    }

    /**
     * Find JS or CSS assets in the input HTML
     * @param type $type
     */
    private function _find_assets($html, $type) {
        //Remove comments
        $html = preg_replace('~<!--.*?-->~s', '', $html);

        //Find tags
        switch ($type) {
            case 'js':
                $tags = array('script');
                $attr_external = 'src';
                break;

            case 'css':
                $tags = array('link');
                $attr_external = 'href';
                break;
        }

        $found = array();

        $matches = null;
        $pattern = '<(?<tag>' . implode('|', $tags) . ')(?<attrs>[^>]*?)(/>|>(?<content>.*?)</\k<tag>>)';
        $attr_pattern = '(\w+)\s*=\s*((["\']).*?\3|[^\'">\s]+?)';
        if (preg_match_all("~$pattern~is", $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs = array();

                //Parse attrs
                if (preg_match_all("~$attr_pattern~is", $match['attrs'], $attr_matches, PREG_SET_ORDER)) {
                    foreach ($attr_matches as $attr_match) {
                        $attrs[$attr_match[1]] = trim($attr_match[2], '"\'');
                    }
                }

                $found[] = array(
                    'original' => $match[0],
                    'tag' => $match['tag'],
                    'attrs' => $attrs,
                    'content' => $match['content'],
                    'external' => isset($attrs[$attr_external]) ? $attrs[$attr_external] : NULL
                );
            }
        }

        return $found;
    }

    private function _suitable_for_join($asset, $mode) {
        //Only join external assets
        if (!isset($asset['external']) || !empty($asset['content'])) {
            return FALSE;
        }
        $url = $asset['external'];

        //Check if URL is in the current domain
        if ($this->_is_absolute($url) && strpos($url, get_site_url()) === FALSE) {
            return FALSE;
        }

        //Check if it is a stylesheet
        if ($mode == 'css') {
            if (!isset($asset['attrs']['rel']) || stristr($asset['attrs']['rel'], 'stylesheet') === FALSE || (isset($asset['attrs']['media']) && stristr($asset['attrs']['media'], 'print') !== FALSE)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    private function _tinyfier_url($mode, $assets) {
        $normalized_assets = array();

        foreach ($assets as $asset) {
            //Remove query
            $asset = preg_replace('/\?.*$/', '', $asset);

            //Remove blog url
            $asset = ltrim(str_replace(get_site_url(), '', $asset), '/');

            $normalized_assets[] = $asset;
        }

        return content_url('assets.php/' . implode(',', $normalized_assets), __FILE__);
    }

    private function _is_absolute($url) {
        if (!empty($url)) {
            if (strpos($url, '://') !== FALSE && preg_match('#^\w+:\/\/#', $url)) {
                return TRUE;
            }

            if (strlen($url) > 2 && $url[0] == '/' && $url[1] == '/') {
                return TRUE;
            }
        }
        return FALSE;
    }

    /* Default settings */

    public static function default_settings() {
        return array(
            'minify_css' => TRUE,
            'minify_js' => TRUE,
            'minify_html' => FALSE,
            
            'async_js' => TRUE,
            'safe_css_order' => TRUE,
        );
    }

    /* Install/Uninstall routines */

    private static function _get_paths(&$cache_dir, &$loader_path, &$tinyfier) {
        $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'tinyfier-cache'; //Store cache in wp-content/cache/tinyfier
        $loader_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'assets.php';
        $tinyfier = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Tinyfier' . DIRECTORY_SEPARATOR . 'tinyfier.php';
    }

    public static function install() {
        //Place assets loader
        self::_get_paths($cache_dir, $loader_path, $tinyfier);

        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755);
        }

        $error_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'tinyfier_error.txt';

        $php = '<?php
$src_folder = ' . var_export(get_home_path(), TRUE) . '; //Wordpress root path
$cache_dir = ' . var_export($cache_dir, TRUE) . '; //Cache path
$error_log = ' . var_export($error_path, TRUE) . '; //Error log path

require ' . var_export($tinyfier, TRUE) . ';';

        file_put_contents($loader_path, $php);
    }

    /**
     * Tinyfier must be the last plugin, in order to process the ob_ buffer
     * before any other caching plugin
     */
    public static function set_plugin_order() {
        // ensure path to this file is via main wp plugin path
        $wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR . "/$2", __FILE__);
        $this_plugin = plugin_basename(trim($wp_path_to_this_file));
        $active_plugins = get_option('active_plugins');
        $this_plugin_key = array_search($this_plugin, $active_plugins);
        if ($this_plugin_key !== FALSE) {
            array_splice($active_plugins, $this_plugin_key, 1);
            array_push($active_plugins, $this_plugin);
            update_option('active_plugins', $active_plugins);
        }
    }

    public static function uninstall() {
        //Remove assets loader
        self::_get_paths($cache_dir, $loader_path, $tinyfier);

        if (is_dir($cache_dir)) {
            self::_rrmdir($cache_dir);
        }
        if (file_exists($loader_path)) {
            unlink($loader_path);
        }
    }

    private static function _rrmdir($dir) {
        foreach (glob($dir . '/*') as $file) {
            if (is_dir($file)) {
                rrmdir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

}

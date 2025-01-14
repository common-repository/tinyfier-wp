<?php

/**
 * Tools for HTML optimization and compression
 */
abstract class Tinyfier_HTML_Tool {

    private static $_settings;

    /**
     * Remove whitespaces from HTML code
     * @param string $html
     * @param boolean $compress_all Compress embedded css and js code
     * @return string
     */
    public static function process($html, array $settings = array()) {
        require_once dirname(__FILE__) . '/Minify_HTML.php';

        $settings = self::$_settings = $settings + array(
            'compress_all' => TRUE,
            'css' => array(),
            'js' => array(),
            'markers' => array(
                '<?'
            ),
            'external_services' => TRUE,
        );

        if ($settings['compress_all']) {
            require_once dirname(dirname(__FILE__)) . '/CSS/Tool.php';
            require_once dirname(dirname(__FILE__)) . '/JS/Tool.php';



            return Minify_HTML::minify($html, array(
                        'cssMinifier' => array(__CLASS__, '_compress_inline_css'),
                        'jsMinifier' => array(__CLASS__, '_compress_inline_js')
            ));
        } else {
            return Minify_HTML::minify($html);
        }
    }

    /**
     * Compress inline CSS code found in a HTML file.
     * Only por internal usage.
     * @access private
     */
    public static function _compress_inline_css($css) {
        if (self::_has_mark($css)) {
            return $css;
        } else {
            return Tinyfier_CSS_Tool::process($css, self::$_settings['css'] + array(
                        'less' => FALSE,
                        'external_services' => self::$_settings['external_services']
            ));
        }
    }

    /**
     * Compress inline JS code found in a HTML file.
     * Only por internal usage.
     * @access private
     */
    public static function _compress_inline_js($js) {
        if (self::_has_mark($js)) {
            return $js;
        } else {
            return Tinyfier_JS_Tool::process($js, self::$_settings['js'] + array(
                        'external_services' => self::$_settings['external_services']
            ));
        }
    }

    /**
     * Comprobar si el código tiene alguna de las marcas establecidas que evitan su compresión.
     * Se utiliza para evitar que fragmentos de código que lleven incustrado código PHP
     * se compriman y den lugar a pérdida de datos
     */
    private static function _has_mark($code) {
        foreach (self::$_settings['markers'] as $mark) {
            if (strpos($code, $mark) !== FALSE) {
                return TRUE;
            }
        }
        return FALSE;
    }

}

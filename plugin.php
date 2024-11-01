<?php

/**
 * Plugin Name: tinyfier-wp
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: Make your wordpress instalation fly. Once enabled, this plugin will combine, compress and optimize JS, CSS and HTML files to improve page load time.
 * Text Domain: tinyfier-wp
 * Version: 0.1
 * Author: ideatic
 * Author URI: http://www.ideatic.net
 * License: GPL2
 */
/*
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define('TINYFIER_TEXT_DOMAIN', 'tinyfier-wp');

require 'TinyfierWP.php';

$instance = new TinyfierWP();
$instance->exec();

register_activation_hook(__FILE__, array('TinyfierWP', 'install'));
register_deactivation_hook(__FILE__, array('TinyfierWP', 'uninstall'));
add_action('activated_plugin', array('TinyfierWP', 'set_plugin_order'));


if (is_admin()) {
    require 'TinyfierSettings.php';
     new TinyfierSettings();
}
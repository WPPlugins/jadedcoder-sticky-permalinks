<?php
/*
Copyright 2007 Matthew Horner (email: jcsp@jadedcoder.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

Plugin Name: jadedcoder Sticky Permalinks
Plugin URI: http://jadedcoder.com/wordpress-plugin-jadedcoder-sticky-permalinks/
Description: Keeps a history of your permalinks no matter what changes you make on your site, and redirects old links to the new ones.
Version: 0.1beta
Author: MatthewHorner
Author URI: http://jadedcoder.com/
*/

require_once dirname( __FILE__ ) . '/Jcsp.php';

$jcsp = new Jcsp();

add_action( 'activate_jadedcoder-sticky-permalinks/jadedcoder-sticky-permalinks.php', array( &$jcsp, 'ensureTables' ) );
add_action( 'edit_category', array( &$jcsp, 'categoryUpdated' ) );
add_action( 'edit_post',     array( &$jcsp, 'postUpdated' ) );
add_action( 'init', array( &$jcsp, 'tryRedirect' ) );
?>
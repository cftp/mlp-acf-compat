<?php

/*
Plugin Name: Advanced Custom Fields and Multilingual Press Compatibility
Plugin URI: https://github.com/cftp/mlp-acf-compat
Description: Harmony between MLP v2 and ACF v4 (prevents ACF fields getting synced between translations, and overwriting each other)
Network: true
Version: 0.1
Author: Code for the People Ltd
Author URI: http://codeforthepeople.com/
*/
 
/*  Copyright 2014 Code for the People Ltd

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

*/



/**
 *
 *
 **/
class CFTP_MLP_ACF_Fixes {

	/**
	 * Singleton stuff.
	 *
	 * @access @static
	 *
	 * @return CFTP_MLP_ACF_Fixes object
	 */
	static public function init() {
		// return false;
		static $instance = false;

		if ( ! $instance )
			$instance = new CFTP_MLP_ACF_Fixes;

		return $instance;

	}

	/**
	 * Class constructor
	 *
	 * @return null
	 */
	public function __construct() {
		add_filter( 'mlp_pre_insert_post_meta', array( $this, 'filter_mlp_pre_insert_post_meta' ), 10, 2 );
		add_action( 'switch_blog', array( $this, 'action_switch_blog' ), 10, 2 );

		$this->acf_nonce = false;
	}

	// HOOKS
	// =====

	/**
	 * Hooks the WP action switch_blog to remove the acf_nonce
	 * index in $_POST when we are not in the "original"
	 * blog context, i.e. we have switched away. This prevents
	 * ACF from saving data in the wrong context, and overwriting
	 * translations. Bit hacky, but it works.
	 *
	 * @action switch_blog
	 * @param int $new_blog_id The blog ID we have switched to
	 * @param int $prev_blog_id The blog ID we have switched from
	 * @return void
	 * @author Simon Wheatley
	 **/
	function action_switch_blog( $new_blog_id, $prev_blog_id ) {
		if ( ! $this->original_blog_id ) {
			$this->original_blog_id = $GLOBALS[ '_wp_switched_stack' ][ 0 ];
		}
		if ( $new_blog_id != $this->original_blog_id ) {
			$this->acf_nonce = $_POST[ 'acf_nonce' ];
			unset( $_POST[ 'acf_nonce' ] );
		} else if ( $this->acf_nonce ) {
			$_POST[ 'acf_nonce' ] = $this->acf_nonce;
		}
	}

	/**
	 * MLP sync the value of all post meta fields across all
	 * translations of a post, this is great until you want
	 * to translate the value of a post meta field (i.e. for
	 * ACF fields). This method hooks the WP filter 
	 * mlp_pre_insert_post_meta to remove all ACF fields 
	 * from the synced meta.
	 *
	 * @filter mlp_pre_insert_post_meta
	 *
	 * @param array $meta An array of post meta data to sync to the related (translated) posts
	 * @return array $meta The meta data to sync
	 * @author Simon Wheatley
	 **/
	public function filter_mlp_pre_insert_post_meta( $meta, $context ) {
		// @TODO: Provide a way to filter the ACF keys which are removed
		// This would allow some ACF fields, e.g. map location, which do
		// not require translation, to be synced.
		$acf_keys = $this->guess_acf_keys( $meta );
		foreach ( $acf_keys as $key ) {
			unset( $meta[ $key ] );
		}
		return $meta;	
	}

	// UTILITIES
	// =========

	/**
	 *
	 *
	 * @param $meta
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function guess_acf_keys( $meta ) {
		$acf_keys = array();
		foreach ( $meta as $key => $value ) {
			// We reckon that an ACF field:
			// Starts with _
			// AND Another meta exists which is named the same, except no preceding _
			// AND that other meta has a value which starts with field_
			if ( '_' == substr( $key, 0, 1 ) && isset( $meta[ substr( $key, 1 ) ] ) && 'field_' == substr( $value, 0, 6 ) ) {
				$acf_keys[] = $key;
				$acf_keys[] = substr( $key, 1 );
			}
		}
		return $acf_keys;
	}

}

// Initiate the singleton
CFTP_MLP_ACF_Fixes::init();

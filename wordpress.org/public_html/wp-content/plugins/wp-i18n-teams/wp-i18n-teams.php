<?php
/*
Plugin Name: WP I18N Teams
Description: Provides shortcodes for displaying details about translation teams.
Version:     1.0
License:     GPLv2 or later
Author:      WordPress.org
Author URI:  http://wordpress.org/
Text Domain: wporg
*/

class WP_I18n_Teams {
	const TEAM_PAGE = 'https://make.wordpress.org/polyglots/teams/?locale=%s';

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Attaches hooks and registers shortcodes once plugins are loasded.
	 */
	public function plugins_loaded() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_shortcode( 'wp-locales',      array( $this, 'wp_locales' ) );

		add_filter( 'term_link', array( $this, 'link_locales' ), 10, 3 );
	}

	/**
	 * Links #locale to the teams page.
	 *
	 * @param string $termlink Term link URL.
	 * @param object $term     Term object.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string URL to teams page of a locale.
	 */
	public function link_locales( $termlink, $term, $taxonomy ) {
		if ( 'post_tag' !== $taxonomy ) {
			return $termlink;
		}

		static $available_locales;

		if ( ! isset( $available_locales ) ) {
			$available_locales = self::get_locales();
			$available_locales = wp_list_pluck( $available_locales, 'wp_locale' );
			$available_locales = array_flip( $available_locales );
		}

		if ( isset( $available_locales[ $term->name ] ) || isset( $available_locales[ $term->slug ] ) ) {
			return sprintf( self::TEAM_PAGE, $term->name );
		}

		return $termlink;
	}

	/**
	 * Enqueue JavaScript and CSS
	 */
	public function enqueue_assets() {
		if ( is_singular() && false !== strpos( get_post()->post_content, '[wp-locales' ) ) {
			wp_enqueue_style( 'wp-i18n-teams', plugins_url( 'css/i18n-teams.css', __FILE__ ), array(), 13 );
			wp_enqueue_script( 'wp-i18n-teams', plugins_url( 'js/i18n-teams.js', __FILE__ ), array( 'jquery', 'o2-app' ), 5 );
		}
	}

	/**
	 * Render the [wp-locales] shortcode.
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	public function wp_locales( $attributes ) {
		ob_start();

		if ( empty( $_GET['locale'] ) ) {
			$locales = self::get_locales();
			$locale_data = $this->get_locales_data();
			$percentages = $this->get_core_translation_data();
			$language_packs_data = $this->get_language_packs_data();
			require( __DIR__ . '/views/all-locales.php' );
		} else {
			require_once GLOTPRESS_LOCALES_PATH;
			$locale = GP_Locales::by_field( 'wp_locale', $_GET['locale'] );
			if ( $locale ) {
				$locale_data = $this->get_extended_locale_data( $locale );
				require( __DIR__ . '/views/locale-details.php' );
			} else {
				printf(
					'<div class="callout callout-warning"><p>%s</p><p><a href="%s">%s</a></p></div>',
					sprintf(
						__( 'Locale %s doesn&#8217;t exist.', 'wporg' ),
						'<code>' . esc_html( $_GET['locale'] ) . '</code>'
					),
					esc_url( get_permalink() ),
					__( 'Return to All Locales', 'wporg' )
				);
			}
		}

		return ob_get_clean();
	}

	/**
	 * Get GlotPress locales that have a wp_locale, sorted alphabetically.
	 *
	 * @return array
	 */
	protected static function get_locales() {
		require_once GLOTPRESS_LOCALES_PATH;

		$locales = GP_Locales::locales();
		$locales = array_filter( $locales, array( __CLASS__, 'filter_locale_for_wp' ) );
		unset( $locales['en'] );
		usort( $locales, array( __CLASS__, 'sort_locales' ) );

		return $locales;
	}

	/**
	 * Remove locales that are missing a wp_locale.
	 *
	 * This is a callback for array_filter().
	 *
	 * @param GP_Locale $element
	 *
	 * @return bool
	 */
	protected static function filter_locale_for_wp( $element ) {
		return isset( $element->wp_locale );
	}

	/**
	 * Sort GlotPress locales alphabetically by the English name.
	 *
	 * @param GP_Locale $a
	 * @param GP_Locale $b
	 *
	 * @return int
	 */
	protected static function sort_locales( $a, $b ) {
		return strcmp( $a->english_name, $b->english_name );
	}

	/**
	 * Gather all the required data and cache it.
	 */
	public function get_locales_data() {
		global $wpdb;

		$cache = get_transient( 'wp_i18n_teams_locales_data' );
		if ( false !== $cache ) {
			return $cache;
		}

		$gp_locales = self::get_locales();
		$translation_data = $this->get_core_translation_data();
		$language_packs_data = $this->get_language_packs_data();
		$locale_data = array();

		$statuses = array(
			'no-wp-project'      => 0,
			'no-site'            => 0,
			'no-releases'        => 0,
			'latest'             => 0,
			'minor-behind'       => 0,
			'major-behind-one'   => 0,
			'major-behind-many'  => 0,
			'translated-100'     => 0,
			'translated-95'      => 0,
			'translated-90'      => 0,
			'translated-50'      => 0,
			'translated-50-less' => 0,
			'has-language-pack'  => 0,
			'no-language-pack'   => 0,
		);

		$wporg_data = $wpdb->get_results( 'SELECT locale, subdomain, latest_release FROM wporg_locales ORDER BY locale', OBJECT_K );

		foreach ( $gp_locales as $locale ) {
			$subdomain = $latest_release = '';
			if ( ! empty( $wporg_data[ $locale->wp_locale ] ) ) {
				$subdomain = $wporg_data[ $locale->wp_locale ]->subdomain;
				$latest_release = $wporg_data[ $locale->wp_locale ]->latest_release;
			}
			$release_status = self::get_locale_release_status( $subdomain, $latest_release );
			$statuses[ $release_status ]++;

			if ( isset( $translation_data[ $locale->wp_locale ] ) ) {
				$translation_status = self::get_locale_translation_status( $translation_data[ $locale->wp_locale ] );
			} else {
				$translation_status = 'no-wp-project';
			}
			$statuses[ $translation_status ]++;

			if ( isset( $language_packs_data[ $locale->wp_locale ] ) ) {
				$language_pack_status = 'has-language-pack';
			} else {
				$language_pack_status = 'no-language-pack';
			}
			$statuses[ $language_pack_status ]++;

			$sites = get_sites( [
				'locale'     => $locale->wp_locale,
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'orderby'    => 'path_length',
				'number'     => '',
			] );

			$locale_data[ $locale->wp_locale ] = array(
				'release_status'       => $release_status,
				'translation_status'   => $translation_status,
				'language_pack_status' => $language_pack_status,
				'sites'                => $sites,
				'subdomain'            => $subdomain,
				'rosetta_site_url'     => "https://$subdomain.wordpress.org/",
				'latest_release'       => $latest_release ? $latest_release : false,
			);
		}

		$locale_data['status_counts'] = $statuses;
		$locale_data['status_counts']['all'] = count( $gp_locales );
		set_transient( 'wp_i18n_teams_locales_data', $locale_data, 900 );
		return $locale_data;
	}

	public function get_language_packs_data() {
		global $wpdb;

		$cache = get_transient( 'wp_i18n_teams_language_packs_data' );
		if ( false !== $cache ) {
			return $cache;
		}

		$language_packs = $wpdb->get_results( "SELECT language AS locale, version FROM `language_packs` WHERE `type` = 'core' AND `active` = 1 AND `version` NOT LIKE '%-%'" );

		$language_packs_data = array();
		foreach ( $language_packs as $pack ) {
			if ( ! isset( $language_packs_data[ $pack->locale ] ) ) {
				$language_packs_data[ $pack->locale ] = array();
			}

			$language_packs_data[ $pack->locale ][] = $pack->version;
		}

		set_transient( 'wp_i18n_teams_language_packs_data', $language_packs_data, 900 );
		return $language_packs_data;
	}

	public function get_extended_locale_data( $locale ) {
		$locales_data = $this->get_locales_data();
		$locale_data = $locales_data[ $locale->wp_locale ];
		$locale_data['localized_core_url'] = $locale_data['language_pack_url'] = false;

		$latest_release = $locale_data['latest_release'];
		if ( $latest_release ) {
			$locale_data['localized_core_url'] = sprintf( 'https://downloads.wordpress.org/release/%s/wordpress-%s.zip', $locale->wp_locale, $latest_release );
			$language_packs_data = $this->get_language_packs_data();

			if ( version_compare( $latest_release, '4.0', '>=' ) && ! empty( $language_packs_data[ $locale->wp_locale ] ) ) {
				list( $x, $y ) = explode( '.', $latest_release );
				$latest_branch = "$x.$y";

				$pack_version = null;
				if ( in_array( $latest_release, $language_packs_data[ $locale->wp_locale ] ) ) {
					$pack_version = $latest_release;
				} elseif ( in_array( $latest_branch, $language_packs_data[ $locale->wp_locale ] ) ) {
					$pack_version = $latest_branch;
				}

				if ( $pack_version ) {
					$locale_data['language_pack_version'] = $pack_version;
					$locale_data['language_pack_url'] = sprintf( 'https://downloads.wordpress.org/translation/core/%s/%s.zip', $pack_version, $locale->wp_locale );
				}
			}
		}

		$contributors = $this->get_contributors( $locale );
		$locale_data['locale_managers'] = $contributors['locale_managers'];
		$locale_data['validators'] = $contributors['validators'];
		$locale_data['project_validators'] = $contributors['project_validators'];
		$locale_data['translators'] = $contributors['translators'];
		$locale_data['translators_past'] = $contributors['translators_past'];

		return $locale_data;
	}

	/**
	 * Get the translators and validators for the given locale.
	 *
	 * @param GP_Locale $locale
	 * @return array
	 */
	public function get_contributors( $locale ) {
		$cache = wp_cache_get( 'contributors-data:' . $locale->wp_locale, 'wp-i18n-teams' );
		if ( false !== $cache ) {
			return $cache;
		}

		// Editors are only assigned to the parent locale.
		$parent_locale = null;
		if ( isset( $locale->root_slug ) ) {
			$parent_locale = GP_Locales::by_slug( $locale->root_slug );
		}

		$contributors = [];
		$contributors['locale_managers'] = $this->get_locale_managers( $parent_locale ?? $locale );
		$contributors['validators'] = $this->get_general_translation_editors( $parent_locale ?? $locale );
		$contributors['project_validators'] = $this->get_project_translation_editors( $parent_locale ?? $locale );
		$contributors['translators'] = $this->get_translation_contributors( $locale, 365 ); // Contributors from the past year
		$contributors['translators_past'] = array_diff_key( $this->get_translation_contributors( $locale ), $contributors['translators'] );

		wp_cache_set( 'contributors-data:' . $locale->wp_locale, $contributors, 'wp-i18n-teams', 2 * HOUR_IN_SECONDS );

		return $contributors;
	}

	public function get_core_translation_data() {
		$cache = get_transient( 'core_translation_data' );
		if ( false !== $cache ) {
			return $cache;
		}

		$projects = array( 'wp/dev', 'wp/dev/cc', 'wp/dev/admin', 'wp/dev/admin/network' );
		$counts = $percentages = array();
		foreach ( $projects as $project ) {
			$results = json_decode( file_get_contents( 'https://translate.wordpress.org/api/projects/' . $project ) );
			foreach ( $results->translation_sets as $set ) {

				if ( ! isset( $set->wp_locale ) ) {
					continue;
				}

				$wp_locale = $set->wp_locale;
				if ( $set->slug !== 'default' ) {
					$wp_locale = $wp_locale . '_' . $set->slug;
				}

				if ( ! isset( $counts[ $set->wp_locale ] ) ) {
					$counts[ $wp_locale ] = 0;
				}
				$counts[ $wp_locale ] += (int) $set->percent_translated;
			}
		}

		foreach ( $counts as $locale => $percent_translated ) {
			// English locales don't have wp/dev/cc.
			$projects_count = 0 === strpos( $locale, 'en_' ) ? 3 : 4;

			/*
			 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
			 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
			 */
			$percent_complete = 100 / ( 100 * $projects_count ) * $percent_translated;
			$percent_complete = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );

			$percentages[ $locale ] = $percent_complete;
		}

		set_transient( 'core_translation_data', $percentages, 900 );

		return $percentages;
	}

	/**
	 * Get the locale managers for the given locale.
	 *
	 * @param GP_Locale $locale
	 * @return array
	 */
	private function get_locale_managers( $locale ) {
		$locale_managers = [];

		$result = get_sites( [
			'locale'     => $locale->wp_locale,
			'network_id' => WPORG_GLOBAL_NETWORK_ID,
			'path'       => '/',
			'fields'     => 'ids',
			'number'     => '1',
		] );
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return $locale_managers;
		}

		$users = get_users( [
			'blog_id'     => $site_id,
			'role'        => 'locale_manager',
			'count_total' => false,
		] );

		foreach ( $users as $user ) {
			$locale_managers[ $user->user_nicename ] = $this->prepare_user( $user );
		}

		uasort( $locale_managers, [ $this, '_sort_display_name_callback' ] );

		return $locale_managers;
	}

	/**
	 * Get the general translation editors for the given locale.
	 *
	 * @param GP_Locale $locale
	 * @return array
	 */
	private function get_general_translation_editors( $locale ) {
		$editors = [];

		$result = get_sites( [
			'locale'     => $locale->wp_locale,
			'network_id' => WPORG_GLOBAL_NETWORK_ID,
			'path'       => '/',
			'fields'     => 'ids',
			'number'     => '1',
		] );
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return $editors;
		}

		$users = get_users( [
			'blog_id'     => $site_id,
			'role'        => 'general_translation_editor',
			'count_total' => false,
		] );

		foreach ( $users as $user ) {
			$editors[ $user->user_nicename ] = $this->prepare_user( $user );
		}

		uasort( $editors, [ $this, '_sort_display_name_callback' ] );

		return $editors;
	}

	/**
	 * Get the general translation editors for the given locale.
	 *
	 * @param GP_Locale $locale
	 * @return array
	 */
	private function get_project_translation_editors( $locale ) {
		$editors = [];

		$result = get_sites( [
			'locale'     => $locale->wp_locale,
			'network_id' => WPORG_GLOBAL_NETWORK_ID,
			'path'       => '/',
			'fields'     => 'ids',
			'number'     => '1',
		] );
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return $editors;
		}

		$users = get_users( [
			'blog_id'     => $site_id,
			'role'        => 'translation_editor',
			'count_total' => false,
		] );

		foreach ( $users as $user ) {
			$editors[ $user->user_nicename ] = $this->prepare_user( $user );
		}

		uasort( $editors, [ $this, '_sort_display_name_callback' ] );

		return $editors;
	}

	/**
	 * Prepares user objects for output.
	 *
	 * @param \WP_User $user The user.
	 * @return array List of user data.
	 */
	private function prepare_user( $user ) {
		if ( $user->display_name && $user->display_name !== $user->user_nicename ) {
			return [
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'nice_name'    => $user->user_nicename,
				'slack'        => self::get_slack_username( $user->ID ),
			];
		} else {
			return [
				'display_name' => $user->user_nicename,
				'email'        => $user->user_email,
				'nice_name'    => $user->user_nicename,
				'slack'        => self::get_slack_username( $user->ID ),
			];
		}
	}

	/**
	 * Get the translation contributors for the given locale.
	 *
	 * @param GP_Locale $locale
	 * @return array
	 */
	private function get_translation_contributors( $locale, $max_age_days = null ) {
		global $wpdb;

		$contributors = array();

		$date_constraint = '';
		if ( null !== $max_age_days ) {
			$date_constraint = $wpdb->prepare( " AND date_modified >= CURRENT_DATE - INTERVAL %d DAY", $max_age_days );
		}

		[ $locale, $locale_slug ] = array_merge( explode( '/', $locale->slug ), [ 'default' ] );

		$users = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM translate_user_translations_count WHERE accepted > 0 AND locale = %s AND locale_slug = %s",
			$locale,
			$locale_slug
		) . $date_constraint );

		if ( ! $users ) {
			return $contributors;
		}

		$user_data = $wpdb->get_results( "SELECT user_nicename, display_name, user_email FROM $wpdb->users WHERE ID IN (" . implode( ',', $users ) . ")" );
		foreach ( $user_data as $user ) {
			if ( $user->display_name && $user->display_name !== $user->user_nicename ) {
				$contributors[ $user->user_nicename ] = array(
					'display_name' => $user->display_name,
					'nice_name'    => $user->user_nicename,
				);
			} else {
				$contributors[ $user->user_nicename ] = array(
					'display_name' => $user->user_nicename,
					'nice_name'    => $user->user_nicename,
				);
			}
		}

		uasort( $contributors, array( $this, '_sort_display_name_callback' ) );

		return $contributors;
	}

	/**
	 * Determine the release status of the given locale,
	 *
	 * @param string $rosetta_site_url
	 * @param string $latest_release
	 *
	 * @return string
	 */
	protected static function get_locale_release_status( $rosetta_site_url, $latest_release ) {
		if ( ! $rosetta_site_url ) {
			return 'no-site';
		}

		if ( ! $latest_release ) {
			return 'no-releases';
		}

		$one_lower = WP_CORE_LATEST_RELEASE - 0.1;

		if ( $latest_release == WP_CORE_LATEST_RELEASE ) {
			return 'latest';
		} elseif ( substr( $latest_release, 0, 3 ) == substr( WP_CORE_LATEST_RELEASE, 0, 3 ) ) {
			return 'minor-behind';
		} elseif ( substr( $latest_release, 0, 3 ) == substr( $one_lower, 0, 3 ) ) {
			return 'major-behind-one';
		} else {
			return 'major-behind-many';
		}
	}

	/**
	 * Determine the translation status of the given locale.
	 *
	 * @param int $percent_translated
	 *
	 * @return string
	 */
	protected static function get_locale_translation_status( $percent_translated ) {
		if ( $percent_translated == 100 ) {
			return 'translated-100';
		} elseif ( $percent_translated >= 95 ) {
			return 'translated-95';
		} elseif ( $percent_translated >= 90 ) {
			return 'translated-90';
		} elseif ( $percent_translated >= 50 ) {
			return 'translated-50';
		} else {
			return 'translated-50-less';
		}
	}

	/**
	 * Get the Slack username for a .org user.
	 *
	 * @param int $user_id
	 *
	 * @return string
	 */
	protected static function get_slack_username( $user_id ) {
		global $wpdb;

		$slack_username = '';

		$data = $wpdb->get_var( $wpdb->prepare( "SELECT profiledata FROM slack_users WHERE user_id = %d", $user_id ) );
		if ( $data && ( $data = json_decode( $data, true ) ) ) {
			if ( !empty( $data['profile']['display_name'] ) && empty( $data['deleted'] ) ) {
				// Optional Display Name field.
				$slack_username = $data['profile']['display_name'];
			} elseif ( !empty( $data['profile']['real_name'] ) && empty( $data['deleted'] ) ) {
				// Fall back to "Full Name" field.
				$slack_username = $data['profile']['real_name'];
			}
		}

		return $slack_username;
	}

	public function _sort_display_name_callback( $a, $b ) {
		return strnatcasecmp( $a['display_name'], $b['display_name'] );
	}
}

$GLOBALS['wp_i18n_teams'] = new WP_I18n_Teams();

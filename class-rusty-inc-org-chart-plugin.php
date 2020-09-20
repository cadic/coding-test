<?php

require_once __DIR__ . '/class-rusty-inc-org-chart-tree.php';
require_once __DIR__ . '/class-rusty-inc-org-chart-sharing.php';

/**
 * Responsible for the WordPress plumbing -- getting the page running, output of JS
 */
class Rusty_Inc_Org_Chart_Plugin {

	public const OPTION_NAME = 'rusty-inc-org-chart-tree';
	public const DEFAULT_ORG_CHART = [
		[ 'id' => 1, 'name' => 'Rusty Corp.', 'emoji' => '🐕' ,'parent_id' => null ],
		[ 'id' => 2, 'name' => 'Food', 'emoji' => '🥩',  'parent_id' => 1 ],
		[ 'id' => 3, 'name' => 'Canine Therapy', 'emoji' => '😌', 'parent_id' => 1 ],
		[ 'id' => 4, 'name' => 'Massages', 'emoji' => '💆', 'parent_id' => 3 ],
		[ 'id' => 5, 'name' => 'Games', 'emoji' => '🎾', 'parent_id' => 3 ],
	];

	public function __construct() {
		$this->sharing = new Rusty_Inc_Org_Chart_Sharing();
	}

	/**
	 * Registers the initial hooks to get the plugin going, if you're
	 * curious, see https://developer.wordpress.org/plugins/hooks/
	 *
	 * In short, both actions and filters are like events or callbacks. The difference
	 * between them is that the return value from filters is passed to the next callback,
	 * while the return value of actions is ignored. In this plugin we're using mostly actions.
	 */
	public function add_init_action() {
		/* a plugin shouldn't do anything before the "init" hook,
		 * that's why the main initialization code is in the init() method
		 */
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Executed on the "init" WordPress action -- initializes the bulk
	 * of our hooks
	 */
	public function init() {
		$page_hook_suffix = null;

		/* Registers the UI for "Rusty Inc. Org Chart" page linked from the main
		 * wp-admin menu
		 * @see https://developer.wordpress.org/reference/functions/add_menu_page/
		 */
		add_action( 'admin_menu', function() use ( &$page_hook_suffix ) {
			$position = 2; // this means the second one from the top
			$page_hook_suffix = add_menu_page( 'Rusty Inc. Org Chart', 'Rusty Inc. Org Chart', 'publish_posts', 'rusty-inc-org-chart', array( $this, 'org_chart_controller' ), 'dashicons-heart', $position );
			add_action( "admin_footer-{$page_hook_suffix}", [ $this, 'scripts_in_footer' ] );
		} );

		$this->maybe_save_tree();

		/**
		 * Handles routing for the publicly shared page -- only triggered when
		 * we have the right arguments in the URL
		 */
		if ( $this->sharing->does_url_have_valid_key() ) {
			$this->org_chart_controller();
			$this->scripts_in_footer();
			exit;
		}
	}

	/**
	 * Outputs script tags right before closing </body> tag
	 *
	 * We want it in the footer to avoid having to hook on document.onload -- all the DOM we need is
	 * already loaded by now.
	 *
	 * While WordPress has a system to load JavaScript assets, unfortunately it still doesn't support
	 * the ES6 `type=module` convention, so we chose to print the script tags manually in the footer.
	 */
	public function scripts_in_footer() {
		$tree = new Rusty_Inc_Org_Chart_Tree( get_option( self::OPTION_NAME, self::DEFAULT_ORG_CHART ) );
		$tree_js = $tree->get_nested_tree_js();
		$ui_js_url = plugins_url( 'ui.js', __FILE__ );
		$framework_js_url = plugins_url( 'framework.js', __FILE__ );
		$secret_url = $this->sharing->url();
		require __DIR__ . '/admin-page-inline-script.php';
	}

	/**
	 * Callback for add_menu_page() -- outputs the HTML for our org chart UI
	 */
	public function org_chart_controller() {
		require __DIR__ . '/admin-page-template.php';
	}

	/**
	 * Save tree and sharing settings
	 *
	 * @return void
	 */
	public function maybe_save_tree() {
		// Quick dumb solution. Needs to be more secure with nonce verification.
		if ( ! empty( $_POST['tree'] ) ) {
			$tree = new Rusty_Inc_Org_Chart_Tree( array() );
			$tree->update_from_json( stripslashes( $_POST['tree'] ) );
			update_option( self::OPTION_NAME, $tree->get_list_of_teams() );
		}
	}
}

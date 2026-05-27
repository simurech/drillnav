<?php
/**
 * DrillNav sidebar widget.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * WP_Widget subclass that renders contextual navigation in a sidebar.
 */
class Widget extends \WP_Widget {

	public function __construct(
		private readonly Navigator $navigator,
		private readonly Settings  $settings
	) {
		// parent::__construct() is intentionally deferred to register_widget()
		// so that __() calls with our textdomain don't fire before the init hook.
	}

	/**
	 * Registers the widget and asset hooks.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'widgets_init', array( $this, 'register_widget' ) );
	}

	/** Registers the widget with WordPress. */
	public function register_widget(): void {
		parent::__construct(
			'drillnav_widget',
			__( 'DrillNav – Contextual Navigation', 'drillnav-drilldown-navigation' ),
			array(
				'description'                 => __( 'Displays a contextual drill-down navigation based on the current page hierarchy.', 'drillnav-drilldown-navigation' ),
				'customize_selective_refresh' => true,
				'show_instance_in_rest'       => true,
			)
		);
		register_widget( $this );
	}

	/**
	 * Outputs the widget on the front end.
	 *
	 * @param array<string,mixed> $args     Widget display arguments (before_widget etc.).
	 * @param array<string,mixed> $instance Saved widget settings.
	 */
	public function widget( $args, $instance ): void {
		$title = ! empty( $instance['title'] )
			? apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base )
			: '';

		$nav_args = array();
		if ( ! empty( $instance['depth'] ) ) {
			$nav_args['depth'] = absint( $instance['depth'] );
		}
		if ( isset( $instance['show_home'] ) ) {
			$nav_args['show_home'] = (bool) $instance['show_home'];
		}
		if ( ! empty( $instance['post_type'] ) && post_type_exists( $instance['post_type'] ) ) {
			$nav_args['post_type'] = sanitize_key( $instance['post_type'] );
		}

		$nav_data = $this->navigator->get_nav_data( $nav_args );

		if ( empty( $nav_data['current_level'] ) ) {
			return;
		}

		// Enqueue assets (the shortcode class handles deduplication).
		$this->enqueue_assets();

		echo wp_kses_post( $args['before_widget'] );

		if ( $title ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		// $instance is now no longer needed (settings extracted above).
		// Reassign to the unique string ID expected by the navigation template.
		$instance      = 'drillnav-widget-' . $this->number;
		$mobile_toggle = false; // Widgets sit in sidebars; drawer mode not applicable.
		include DRILLNAV_PLUGIN_DIR . 'includes/views/navigation.php';

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Outputs the widget settings form in the admin.
	 *
	 * @param array<string,mixed> $instance Current settings.
	 * @return string
	 */
	public function form( $instance ): string {
		$title     = $instance['title'] ?? '';
		$depth     = absint( $instance['depth'] ?? 0 );
		$show_home = ! empty( $instance['show_home'] );
		$post_type = $instance['post_type'] ?? 'page';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'drillnav-drilldown-navigation' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>"
			>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'depth' ) ); ?>">
				<?php esc_html_e( 'Max depth (0 = unlimited):', 'drillnav-drilldown-navigation' ); ?>
			</label>
			<input
				class="tiny-text"
				id="<?php echo esc_attr( $this->get_field_id( 'depth' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'depth' ) ); ?>"
				type="number"
				min="0"
				value="<?php echo esc_attr( (string) $depth ); ?>"
			>
		</p>
		<p>
			<input
				id="<?php echo esc_attr( $this->get_field_id( 'show_home' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_home' ) ); ?>"
				type="checkbox"
				value="1"
				<?php checked( $show_home ); ?>
			>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_home' ) ); ?>">
				<?php esc_html_e( 'Show home link', 'drillnav-drilldown-navigation' ); ?>
			</label>
		</p>
		<?php
		return '';
	}

	/**
	 * Sanitises and saves the widget settings.
	 *
	 * @param array<string,mixed> $new_instance
	 * @param array<string,mixed> $old_instance
	 * @return array<string,mixed>
	 */
	public function update( $new_instance, $old_instance ): array {
		return array(
			'title'     => sanitize_text_field( $new_instance['title'] ?? '' ),
			'depth'     => absint( $new_instance['depth'] ?? 0 ),
			'show_home' => ! empty( $new_instance['show_home'] ),
			'post_type' => sanitize_key( $new_instance['post_type'] ?? 'page' ),
		);
	}

	/** Enqueues frontend assets (deduped via wp_enqueue). */
	private function enqueue_assets(): void {
		if ( $this->settings->get( 'load_css' ) ) {
			wp_enqueue_style( 'drillnav-frontend' );
		}
		if ( $this->settings->get( 'load_a11y_css' ) ) {
			wp_enqueue_style( 'drillnav-a11y' );
		}
		wp_enqueue_script( 'drillnav-frontend' );
	}
}

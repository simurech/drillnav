<?php
/**
 * Registers and runs all WordPress hooks.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Collects actions and filters, then runs them all at once.
 * This pattern decouples hook registration from execution and
 * makes it easy to unit-test components in isolation.
 */
class Loader {

	/** @var array<array{hook:string,callback:callable,priority:int,args:int}> */
	private array $actions = array();

	/** @var array<array{hook:string,callback:callable,priority:int,args:int}> */
	private array $filters = array();

	/**
	 * Queues a WordPress action.
	 *
	 * @param string   $hook
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Queues a WordPress filter.
	 *
	 * @param string   $hook
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}

	/** Registers all queued actions and filters with WordPress. */
	public function run(): void {
		foreach ( $this->actions as $a ) {
			add_action( $a['hook'], $a['callback'], $a['priority'], $a['accepted_args'] );
		}
		foreach ( $this->filters as $f ) {
			add_filter( $f['hook'], $f['callback'], $f['priority'], $f['accepted_args'] );
		}
	}
}

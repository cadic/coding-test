<?php

/**
 * Represents an org-chart tree
 */
class Rusty_Inc_Org_Chart_Tree {
	private $list_of_teams;

	/**
	 * An index for children quick serch:
	 *
	 * $parent_id => array( $teams )
	 *
	 * @var array
	 */
	private $children = array();

	/**
	 * @param array $list_of_teams an array of teams, where each team is an associative array with at least an `id` and `parent_id` keys
	 */
	public function __construct( $list_of_teams ) {
		$this->list_of_teams = $list_of_teams;
		$this->rebuild_children_index();
	}

	public function rebuild_children_index() {
		// Build the library of parent_id => teams.
		foreach ( $this->list_of_teams as $team ) {
			// Skip the head.
			if ( is_null( $team['parent_id'] ) ) {
				continue;
			}

			if ( isset( $this->children[ $team['parent_id'] ] ) ) {
				// Add team to existing parent.
				$this->children[ $team['parent_id'] ][] = $team;
			} else {
				// Initialize new parent if not exists.
				$this->children[ $team['parent_id'] ] = array( $team );
			}
		}
	}

	public function get_list_of_teams() {
		return $this->list_of_teams;
	}

	/**
	 * Converts the internal representation to a nested representation, for which:
	 * - each node is an associative array with at least the following keys:
	 *   - `id`
	 *   - `children`: an array of the children of the node, each of them a node by itself
	 * - the whole tree is represented by the root
	 *
	 * @return array|null the root of the tree or `null` if the tree is empty
	 */
	public function get_nested_tree( $root = null ) {
		if ( is_null( $root ) ) {
			$root = $this->get_root( $this->list_of_teams );
			if ( is_null( $root ) ) {
				return null;
			}
		}
		$root['children'] = array_map(
			function( $child ) {
				return $this->get_nested_tree( $child );
			},
			$this->get_children( $root )
		);
		return $root;
	}

	/**
	 * Updates list_of_teams from nested tree in JSON format
	 *
	 * @param string $json_string JSON tree representation.
	 * @return void
	 */
	public function update_from_json( $json_string = 'null' ) {
		// Decode string to assoc array.
		$nested_tree = json_decode( $json_string, true );
		$list_of_teams = $this->get_plain_list( $nested_tree );
		$this->list_of_teams = $list_of_teams;
		$this->rebuild_children_index();
	}

	/**
	 * Recursively convert nested tree to plain teams list
	 *
	 * @param array $root Tree root.
	 * @return array
	 */
	public function get_plain_list( $root = null ) {
		$list_of_teams = array();

		if ( ! is_null( $root ) ) {
			// Pick the root element.
			$team = $root;

			// Process children.
			if ( isset( $team['children'] ) && is_array( $team['children'] ) ) {

				foreach ( $team['children'] as $child_team ) {

					// Recursively get plain list for child.
					$child_teams = $this->get_plain_list( $child_team );
					$list_of_teams = array_merge( $list_of_teams, $child_teams );
				}

				unset( $team['children'] );
			}

			$list_of_teams[] = $team;
		}

		return $list_of_teams;
	}

	public function get_nested_tree_js( $root = null ) {
		$root = $this->get_nested_tree( $root );
		// TODO Why don't just change to PHP builtin json_encode 🤔?
		return $this->custom_json_encode( $root );
	}

	public function custom_json_encode( $root ) {
		if ( is_null( $root ) ) {
			return 'null';
		}
		$js = '{';
		foreach( $root as $key => $value ) {
			$js .= '"' . $key . '":';
			if ( $key === 'children' ) {
				$js .= '[' . implode( ', ', array_map( [ $this, 'custom_json_encode' ], $value ) ) . ']';
			} else if ( is_numeric( $value ) ) {
				$js .= $value . ',';
			} else if ( 'emoji' === $key ) {
				$js .= $this->emoji_to_js( $value );
			} else if ( null === $value ) {
				$js .= 'null,';
			} else {
				$js .= '"' . $value . '",';
			}
		}
		$js .= '}';
		return $js;
	}

	private function get_root( $tree ) {
		foreach( $tree as $team ) {
			if ( is_null( $team['parent_id'] ) ) {
				return $team;
			}
		}
		return null;
	}

	private function get_children( $parent ) {
		$children = array();

		// Look up in the index for children of this parent.
		if ( isset( $this->children[ $parent['id'] ] ) ) {
			$children = $this->children[ $parent['id'] ];
		}
		return $children;
	}

	private function emoji_to_js( $emoji ) {
		return '"' . implode( '', array_map( function( $utf16 ) { return '\u' . str_pad( strtolower( sprintf( '%X', $utf16 ) ), 4, '0', STR_PAD_LEFT ); }, $this->emoji_to_utf16_surrogate( $this->utf8_ord( $emoji ) ) ) ) . '",';
	}

	private function emoji_to_utf16_surrogate( $emoji ) {
		if ( $emoji > 0x10000 ) {
			return [ ( ( $emoji - 0x10000 ) >> 10 ) + 0xD800, ( ( $emoji - 0x10000 ) % 0x400 ) + 0xDC00 ];
		} else {
			return [ $emoji ];
		}
	}

	private function utf8_ord( $emoji ) {
		$first_byte = ord( $emoji[0] );
		if ( $first_byte>=0 && $first_byte <=127 ) {
			return $first_byte;
		}
		$second_byte = ord( $emoji[1] );
		if ( $first_byte>=192 && $first_byte<=223 ) {
			return ( $first_byte - 192 ) * 64 + ( $second_byte - 128 );
		}
		$third_byte = ord( $emoji[2] );
		if ( $first_byte>=224 && $first_byte<=239 ) {
			return ( $first_byte - 224 ) * 4096 + ( $second_byte - 128 ) * 64 + ( $third_byte - 128 );
		}
		$fourth_byte = ord( $emoji[3] );
		if ( $first_byte>=240 && $first_byte<=247 ) {
			return ( $first_byte - 240 ) * 262144 + ( $second_byte - 128 ) * 4096 + ( $third_byte - 128 ) * 64 + ( $fourth_byte - 128 );
		}
		return false;
	}
}

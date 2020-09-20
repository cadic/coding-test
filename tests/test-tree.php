<?php
require_once __DIR__ . '/../class-rusty-inc-org-chart-tree.php';

class Rusty_Inc_Org_Chart_Tree_Test extends WP_UnitTestCase {

	public function test_empty_list_returns_null() {
		$this->assertEquals( null, ( new Rusty_Inc_Org_Chart_Tree( [] ) )->get_nested_tree() );
	}

	public function test_only_root_returns_single_node() {
		$only_root = [ [ 'id' => 1, 'name' => 'root', 'parent_id' => null ] ];
		$expected = array_merge( $only_root[0], [ 'children' => [] ] );
		$this->assertEquals( $expected, ( new Rusty_Inc_Org_Chart_Tree( $only_root ) )->get_nested_tree() );
	}

	public function test_three_levels_deep_structure() {
		$list_of_teams = [
			[ 'id' => 2, 'name' => 'Food', 'emoji' => '🥩',  'parent_id' => 1 ],
			[ 'id' => 4, 'name' => 'Massages', 'emoji' => '💆', 'parent_id' => 3 ],
			[ 'id' => 3, 'name' => 'Canine Therapy', 'emoji' => '😌', 'parent_id' => 1 ],
			[ 'id' => 5, 'name' => 'Games', 'emoji' => '🎾', 'parent_id' => 3 ],
			[ 'id' => 1, 'name' => 'Rusty Corp.', 'emoji' => '🐕' ,'parent_id' => null ],
		];
		$expected = [ 'id' => 1, 'name' => 'Rusty Corp.', 'emoji' => '🐕' ,'parent_id' => null, 'children' => [
			[ 'id' => 2, 'name' => 'Food', 'emoji' => '🥩',  'parent_id' => 1, 'children' => [] ],
			[ 'id' => 3, 'name' => 'Canine Therapy', 'emoji' => '😌', 'parent_id' => 1, 'children' => [
				[ 'id' => 4, 'name' => 'Massages', 'emoji' => '💆', 'parent_id' => 3, 'children' => [] ],
				[ 'id' => 5, 'name' => 'Games', 'emoji' => '🎾', 'parent_id' => 3, 'children' => [] ],
			] ],
	    ] ];
		$this->assertEquals( $expected, ( new Rusty_Inc_Org_Chart_Tree( $list_of_teams ) )->get_nested_tree() );
	}

	/**
	 * Creates large tree (3 teams / 9 levels from the CLI example)
	 * and checks if it's converted to nested in less than 5 seconds
	 * (should be enough for most test environments)
	 */
	public function test_really_big_tree() {
		$levels = 9;
		$sub_teams = 3;
		$flat_tree = [ 1 => [ 'id' => 1, 'parent_id' => null, 'emoji' => '🍓', 'name' => 'Land of Nice and Competent People' ] ];
		$next_id = 2;
		$previous_level = [ 1 ];
		for( $level = 0; $level < $levels - 1; $level++ ) {
			$this_level = [];
			foreach( $previous_level as $parent_id ) {
				for ( $sub_team = 0; $sub_team < $sub_teams; $sub_team++ ) {
					$id = $next_id++;
					$flat_tree[$id] = [ 'id' => $id, 'parent_id' => $parent_id, 'emoji' => '👋', 'name' => "Respected Group 🍉 ($id)" ];
					$this_level[] = $id;
				}
			}
			$previous_level = $this_level;
		}

		$start_time = microtime( true );
		$tree = ( new Rusty_Inc_Org_Chart_Tree( $flat_tree ) )->get_nested_tree();
		$end_time = microtime( true );

		$less_than_five_seconds = ( $end_time - $start_time ) < 5;
		$this->assertTrue( $less_than_five_seconds );
	}

}

<?php
/**
 * Add a recent commit count to each component to enable activity-based sorting
 *
 * @copyright The Financial Times Limited [All rights reserved]
 */

class Migration201607281530 extends Migrate {

	public function preDeployUp() {
		$sql = <<<'SQL'

ALTER TABLE `components` CHANGE COLUMN `origami_group` `origami_category` VARCHAR(128)  NULL;

SQL;
		$this->executeSql($sql);
	}
}

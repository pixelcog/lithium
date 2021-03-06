<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2014, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\mocks\data;

class MockCouchModel extends \lithium\data\Model {

	protected $_meta = array('connection' => false);

	protected $_schema = array(
		'someKey' => array()
	);
}

?>
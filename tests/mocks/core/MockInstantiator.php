<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2014, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\mocks\core;

class MockInstantiator extends \lithium\core\Object {

	protected $_classes = array('request' => 'lithium\tests\mocks\core\MockRequest');

	public function instance($name, array $config = array()) {
		return $this->_instance($name, $config);
	}
}

?>
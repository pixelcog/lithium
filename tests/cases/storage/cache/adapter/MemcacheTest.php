<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2014, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\storage\cache\adapter;

use Memcached;
use lithium\storage\Cache;
use lithium\storage\cache\adapter\Memcache;

class MemcacheTest extends \lithium\test\Unit {

	protected $_conn = null;

	/**
	 * Skip the test if the adapter is enabled. If it is not it means the
	 * libmemcached extension is unavailable. Also checks for a running
	 * Memcached server.
	 */
	public function skip() {
		$this->skipIf(!Memcache::enabled(), 'The `Memcache` adapter is not enabled.');

		$conn = new Memcached();
		$conn->addServer('127.0.0.1', 11211);
		$message = 'The memcached daemon does not appear to be running on 127.0.0.1:11211';
		$result = $conn->getVersion();
		$this->skipIf(!$result, $message);
		unset($conn);
	}

	public function setUp() {
		$this->server = array('host' => '127.0.0.1', 'port' => 11211, 'weight' => 100);
		$this->_conn = new Memcached();
		$this->_conn->addServer(
			$this->server['host'], $this->server['port'], $this->server['weight']
		);
		$this->memcache = new Memcache();
	}

	public function tearDown() {
		$this->_conn->flush();
	}

	public function testEnabled() {
		$this->assertTrue(Memcache::enabled());
	}

	public function testSimpleWrite() {
		$key = 'key';
		$data = 'value';
		$keys = array($key => $data);
		$expiry = '+5 seconds';
		$time = strtotime($expiry);

		$result = $this->memcache->write($keys, $expiry);
		$this->assertEqual($keys, $result);
		$this->assertEqual($data, $this->_conn->get($key));

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);

		$key = 'another_key';
		$data = 'more_data';
		$keys = array($key => $data);
		$expiry = '+1 minute';
		$time = strtotime($expiry);

		$expected = $keys;
		$result = $this->memcache->write($keys, $expiry);
		$this->assertEqual($expected, $result);

		$expected = $data;
		$result = $this->_conn->get($key);
		$this->assertEqual($expected, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testWriteExpiryDefault() {
		$memcache = new Memcache(array('expiry' => '+5 seconds'));
		$key = 'default_key';
		$data = 'value';
		$keys = array($key => $data);

		$result = $memcache->write($keys);
		$expected = $keys;
		$this->assertEqual($expected, $result);

		$expected = $data;
		$result = $this->_conn->get($key);
		$this->assertEqual($expected, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testWriteNoExpiry() {
		$keys = array('key1' => 'data1');

		$adapter = new Memcache(array('expiry' => null));
		$expiry = null;

		$result = $adapter->write($keys, $expiry);
		$this->assertTrue($result);

		$result = (boolean) $this->_conn->get('key1');
		$this->assertTrue($result);

		$this->_conn->delete('key1');

		$adapter = new Memcache(array('expiry' => Cache::PERSIST));
		$expiry = Cache::PERSIST;

		$result = $adapter->write($keys, $expiry);
		$this->assertTrue($result);

		$result = (boolean) $this->_conn->get('key1');
		$this->assertTrue($result);

		$this->_conn->delete('key1');

		$adapter = new Memcache();
		$expiry = Cache::PERSIST;

		$result = $adapter->write($keys, $expiry);
		$this->assertTrue($result);

		$result = (boolean) $this->_conn->get('key1');
		$this->assertTrue($result);

		$this->_conn->delete('key1');
	}

	public function testWriteExpiryExpires() {
		$keys = array('key1' => 'data1');
		$expiry = '+5 seconds';
		$this->memcache->write($keys, $expiry);

		$result = (boolean) $this->_conn->get('key1');
		$this->assertTrue($result);

		$this->_conn->delete('key1');

		$keys = array('key1' => 'data1');
		$expiry = '+1 second';
		$this->memcache->write($keys, $expiry);

		sleep(2);

		$result = $this->_conn->get('key1');
		$this->assertFalse($result);
	}

	public function testWriteExpiryTtl() {
		$keys = array('key1' => 'data1');
		$expiry = 5;
		$this->memcache->write($keys, $expiry);

		$result = (boolean) $this->_conn->get('key1');
		$this->assertTrue($result);

		$this->_conn->delete('key1');

		$keys = array('key1' => 'data1');
		$expiry = 1;
		$this->memcache->write($keys, $expiry);

		sleep(2);

		$result = $this->_conn->get('key1');
		$this->assertFalse($result);
	}

	public function testWriteMulti() {
		$expiry = '+1 minute';
		$time = strtotime($expiry);
		$keys = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);

		$result = $this->memcache->write($keys, $expiry);
		$this->assertTrue($result);

		$result = $this->_conn->getMulti(array_keys($keys));
		$expected = $keys;
		$this->assertEqual($expected, $result);

		foreach ($keys as $name => &$value) {
			$result = $this->_conn->delete($name);
			$this->assertTrue($result);
		}
	}

	public function testWriteWithScope() {
		$adapter = new Memcache(array('scope' => 'primary'));

		$keys = array('key1' => 'test1');
		$expiry = '+1 minute';
		$adapter->write($keys, $expiry);

		$expected = 'test1';
		$result = $this->_conn->get('primary:key1');
		$this->assertEqual($expected, $result);

		$result = $this->_conn->get('key1');
		$this->assertFalse($result);
	}

	public function testSimpleRead() {
		$key = 'read_key';
		$data = 'read data';
		$keys = array($key);
		$time = strtotime('+1 minute');

		$result = $this->_conn->set($key, $data, $time);
		$this->assertTrue($result);

		$expected = array($key => $data);
		$result = $this->memcache->read($keys);
		$this->assertEqual($expected, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);

		$key = 'another_read_key';
		$data = 'read data';
		$keys = array($key);
		$time = strtotime('+1 minute');

		$result = $this->_conn->set($key, $data, $time);
		$this->assertTrue($result);

		$expected = array($key => $data);
		$result = $this->memcache->read($keys);
		$this->assertEqual($expected, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testReadMulti() {
		$expiry = '+1 minute';
		$time = strtotime($expiry);
		$keys = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);

		$result = $this->_conn->setMulti($keys, $time);
		$this->assertTrue($result);

		$result = $this->memcache->read(array_keys($keys));
		$expected = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);
		$this->assertEqual($expected, $result);

		foreach ($keys as $name => &$value) {
			$result = $this->_conn->delete($name);
			$this->assertTrue($result);
		}
	}

	public function testReadKeyThatDoesNotExist() {
		$key = 'does_not_exist';
		$keys = array($key);

		$expected = array();
		$result = $this->memcache->read($keys);
		$this->assertIdentical($expected, $result);
	}

	public function testReadWithScope() {
		$adapter = new Memcache(array('scope' => 'primary'));

		$this->_conn->set('primary:key1', 'test1', 60);
		$this->_conn->set('key1', 'test2', 60);

		$keys = array('key1');
		$expected = array('key1' => 'test1');
		$result = $adapter->read($keys);
		$this->assertEqual($expected, $result);
	}

	public function testDelete() {
		$key = 'delete_key';
		$data = 'data to delete';
		$keys = array($key);
		$time = strtotime('+1 minute');
		$this->_conn->set($key, $data, $time);

		$expected = array($key => $data);
		$result = $this->memcache->read($keys);
		$this->assertEqual($expected, $result);

		$result = $this->memcache->delete($keys);
		$this->assertTrue($result);

		$expected = array();
		$result = $this->memcache->read($keys);
		$this->assertIdentical($expected, $result);
	}

	public function testDeleteNonExistentKey() {
		$key = 'delete_key';
		$keys = array($key);

		$params = compact('keys');
		$result = $this->memcache->delete($keys);
		$this->assertFalse($result);
	}

	public function testDeleteWithScope() {
		$adapter = new Memcache(array('scope' => 'primary'));

		$this->_conn->set('primary:key1', 'test1', 60);
		$this->_conn->set('key1', 'test2', 60);

		$keys = array('key1');
		$expected = array('key1' => 'test1');
		$adapter->delete($keys);

		$result = (boolean) $this->_conn->get('key1');
		$this->assertTrue($result);

		$result = $this->_conn->get('primary:key1');
		$this->assertFalse($result);
	}

	public function testDeprecatedConnectionSettings() {
		$servers = array(array('127.0.0.1', 11211, 1));
		$test = new Memcache(compact('servers'));
		$servers[0] = array_combine(array('host', 'port'), array_slice($servers[0], 0, 2));
		$this->assertEqual($servers, $test->connection->getServerList());
	}

	public function testSimpleConnectionSettings() {
		$test = new Memcache(array('host' => '127.0.0.1'));
		$hosts = array(array('host' => '127.0.0.1', 'port' => 11211));
		$this->assertEqual($hosts, $test->connection->getServerList());

		$test = new Memcache(array('host' => '127.0.0.1:11222'));
		$hosts = array(array('host' => '127.0.0.1', 'port' => 11222));
		$this->assertEqual($hosts, $test->connection->getServerList());
	}

	public function testMultiServerConnectionSettings() {
		$test = new Memcache(array('host' => array(
			'127.0.0.1:11222' => 1,
			'127.0.0.2:11223' => 2,
			'127.0.0.3:11224'
		)));
		$hosts = array(
			array('host' => '127.0.0.1', 'port' => 11222),
			array('host' => '127.0.0.2', 'port' => 11223),
			array('host' => '127.0.0.3', 'port' => 11224)
		);
		$this->assertEqual($hosts, $test->connection->getServerList());
	}

	public function testWriteReadAndDeleteRoundtrip() {
		$key = 'write_read_key';
		$data = 'write/read value';
		$keys = array($key => $data);
		$expiry = '+5 seconds';
		$time = strtotime($expiry);

		$result = $this->memcache->write($keys, $expiry);
		$this->assertEqual($data, $result);
		$this->assertEqual($data, $this->_conn->get($key));


		$expected = $keys;
		$result = $this->memcache->read(array_keys($keys));
		$this->assertEqual($expected, $result);

		$result = $this->memcache->delete(array_keys($keys));
		$this->assertTrue($result);

		$this->assertFalse($this->_conn->get($key));
	}

	public function testWriteAndReadNull() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => null
		);
		$result = $this->memcache->write($keys);
		$this->assertTrue($result);

		$expected = $keys;
		$result = $this->memcache->read(array_keys($keys));
		$this->assertEqual($expected, $result);
	}

	public function testWriteAndReadNullMulti() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => null,
			'key2' => 'data2'
		);
		$result = $this->memcache->write($keys);
		$this->assertTrue($result);

		$expected = $keys;
		$result = $this->memcache->read(array_keys($keys));
		$this->assertEqual($expected, $result);

		$keys = array(
			'key1' => null,
			'key2' => null
		);
		$result = $this->memcache->write($keys);
		$this->assertTrue($result);
	}

	public function testClear() {
		$time = strtotime('+1 minute');

		$result = $this->_conn->set('key', 'value', $time);
		$this->assertTrue($result);

		$result = $this->_conn->set('another_key', 'value', $time);
		$this->assertTrue($result);

		$result = $this->memcache->clear();
		$this->assertTrue($result);

		$this->assertFalse($this->_conn->get('key'));
		$this->assertFalse($this->_conn->get('another_key'));
	}

	public function testDecrement() {
		$time = strtotime('+1 minute');
		$key = 'decrement';
		$value = 10;

		$result = $this->_conn->set($key, $value, $time);
		$this->assertTrue($result);

		$result = $this->memcache->decrement($key);
		$this->assertEqual($value - 1, $result);

		$result = $this->_conn->get($key);
		$this->assertEqual($value - 1, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testDecrementNonIntegerValue() {
		$time = strtotime('+1 minute');
		$key = 'non_integer';
		$value = 'no';

		$result = $this->_conn->set($key, $value, $time);
		$this->assertTrue($result);

		$this->memcache->decrement($key);

		$result = $this->_conn->get($key);
		$this->assertEqual(0, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testDecrementWithScope() {
		$adapter = new Memcache(array('scope' => 'primary'));

		$this->_conn->set('primary:key1', 1, 60);
		$this->_conn->set('key1', 1, 60);

		$adapter->decrement('key1');

		$expected = 1;
		$result = $this->_conn->get('key1');
		$this->assertEqual($expected, $result);

		$expected = 0;
		$result = $this->_conn->get('primary:key1');
		$this->assertEqual($expected, $result);
	}

	public function testIncrement() {
		$time = strtotime('+1 minute');
		$key = 'increment';
		$value = 10;

		$this->assertTrue($this->_conn->set($key, $value, $time));

		$result = $this->memcache->increment($key);
		$this->assertEqual($value + 1, $result);
		$this->assertEqual($value + 1, $this->_conn->get($key));

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testIncrementNonIntegerValue() {
		$time = strtotime('+1 minute');
		$key = 'non_integer_increment';
		$value = 'yes';

		$result = $this->_conn->set($key, $value, $time);
		$this->assertTrue($result);

		$this->memcache->increment($key);

		$result = $this->_conn->get($key);
		$this->assertEqual(0, $result);

		$result = $this->_conn->delete($key);
		$this->assertTrue($result);
	}

	public function testIncrementWithScope() {
		$adapter = new Memcache(array('scope' => 'primary'));

		$this->_conn->set('primary:key1', 1, 60);
		$this->_conn->set('key1', 1, 60);

		$adapter->increment('key1');

		$expected = 1;
		$result = $this->_conn->get('key1');
		$this->assertEqual($expected, $result);

		$expected = 2;
		$result = $this->_conn->get('primary:key1');
		$this->assertEqual($expected, $result);
	}

	public function testRespondsTo() {
		$this->assertTrue($this->memcache->respondsTo('touch'));
		$this->assertFalse($this->memcache->respondsTo('foobarbaz'));
	}

	public function testRespondsToParentCall() {
		$this->assertTrue($this->memcache->respondsTo('applyFilter'));
		$this->assertFalse($this->memcache->respondsTo('fooBarBaz'));
	}
}

?>
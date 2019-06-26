<?php

namespace StingerSoft\SymfonyWincache\Tests;

use Psr\Log\NullLogger;
use StingerSoft\SymfonyWincache\Adapter\WincacheAdapter;
use Symfony\Component\Cache\Tests\Adapter\AdapterTestCase;

/**
 * Taken from https://github.com/symfony/cache/blob/master/Tests/Adapter/ApcuAdapterTest.php
 */
class WincacheAdapterTest extends AdapterTestCase {

	protected $skippedTests = [
		'testExpiration' => 'Testing expiration slows down the test suite',
		'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
		'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
	];

	public function createCachePool($defaultLifetime = 0) {
		if(!\function_exists('wincache_ucache_get')) {
			$this->markTestSkipped('WinCache extension is required.');
		}
		if('cli' === \PHP_SAPI && !filter_var(ini_get('wincache.enablecli'), FILTER_VALIDATE_BOOLEAN)) {
//			if('testWithCliSapi' !== $this->getName()) {
				$this->markTestSkipped('wincache.enable_cli=1 is required.');
//			}
		}
		if('/' === \DIRECTORY_SEPARATOR) {
			$this->markTestSkipped('Fails transiently on non Windows.');
		}
		return new WincacheAdapter(str_replace('\\', '.', __CLASS__), $defaultLifetime);
	}

	public function testUnserializable() {
		$pool = $this->createCachePool();
		$item = $pool->getItem('foo');
		$item->set(function() {
		});
		$this->assertFalse($pool->save($item));
		$item = $pool->getItem('foo');
		$this->assertFalse($item->isHit());
	}

	public function testVersion() {
		$namespace = str_replace('\\', '.', \get_class($this));
		$pool1 = new WincacheAdapter($namespace, 0, 'p1');
		$item = $pool1->getItem('foo');
		$this->assertFalse($item->isHit());
		$this->assertTrue($pool1->save($item->set('bar')));
		$item = $pool1->getItem('foo');
		$this->assertTrue($item->isHit());
		$this->assertSame('bar', $item->get());

		$pool2 = new WincacheAdapter($namespace, 0, 'p2');
		$item = $pool2->getItem('foo');
		$this->assertFalse($item->isHit());
		$this->assertNull($item->get());
		$item = $pool1->getItem('foo');
		$this->assertFalse($item->isHit());
		$this->assertNull($item->get());
	}

	public function testNamespace() {
		$namespace = str_replace('\\', '.', \get_class($this));
		$pool1 = new WincacheAdapter($namespace . '_1', 0, 'p1');
		$item = $pool1->getItem('foo');
		$this->assertFalse($item->isHit());
		$this->assertTrue($pool1->save($item->set('bar')));
		$item = $pool1->getItem('foo');
		$this->assertTrue($item->isHit());
		$this->assertSame('bar', $item->get());

		$pool2 = new WincacheAdapter($namespace . '_2', 0, 'p1');
		$item = $pool2->getItem('foo');
		$this->assertFalse($item->isHit());
		$this->assertNull($item->get());
		$item = $pool1->getItem('foo');
		$this->assertTrue($item->isHit());
		$this->assertSame('bar', $item->get());
	}

	public function testWithCliSapi() {
		try {
			// disable PHPUnit error handler to mimic a production environment
			$isCalled = false;
				set_error_handler(function() use (&$isCalled) {
				$isCalled = true;
			});
			$pool = new WincacheAdapter(str_replace('\\', '.', __CLASS__));
			$pool->setLogger(new NullLogger());
			$item = $pool->getItem('foo');
			$item->isHit();
			$pool->save($item->set('bar'));
			$this->assertFalse($isCalled);
		} finally {
			restore_error_handler();
		}
	}
}
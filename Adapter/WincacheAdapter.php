<?php

namespace StingerSoft\SymfonyWincache\Adapter;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class WincacheAdapter extends AbstractAdapter {
	/**
	 * @var MarshallerInterface
	 */
	private $marshaller;

	/**
	 * @throws CacheException if APCu is not enabled
	 */
	public function __construct(string $namespace = '', int $defaultLifetime = 0, string $version = null, MarshallerInterface $marshaller = null) {
		if(!static::isSupported()) {
			throw new CacheException('WinCache is not enabled');
		}

		parent::__construct($namespace, $defaultLifetime);
		if(null !== $version) {
			CacheItem::validateKey($version);
			if(!wincache_ucache_exists($version . '@' . $namespace)) {
				$this->doClear($namespace);
				wincache_ucache_add($version . '@' . $namespace, null);
			}
		}
		$this->marshaller = $marshaller ?? new DefaultMarshaller();
		
	}

	public static function isSupported() {
		return \function_exists('wincache_ucache_get');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch(array $ids) {
		$unserializeCallbackHandler = ini_set('unserialize_callback_func', __CLASS__ . '::handleUnserializeCallback');
		try {
			$values = [];

			foreach(wincache_ucache_get($ids, $ok) ?: [] as $k => $v) {
				if(null !== $v || $ok) {
					$values[$k] = $v;
				}
			}
			return $values;
		} catch(\Error $e) {
			throw new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
		} finally {
			ini_set('unserialize_callback_func', $unserializeCallbackHandler);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHave($id) {
		return wincache_ucache_exists($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear($namespace) {
		if(!isset($namespace[0])) {
			wincache_ucache_clear();
			return true;
		}
		$info = wincache_ucache_info();
		if($info === false) {
			return false;
		}
		foreach($info['ucache_entries'] as $entry) {
			if(!isset($entry['is_session']) && 0 === strpos($entry['key_name'], $namespace)) {
				if(!wincache_ucache_delete($entry['key_name'])) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(array $ids) {
		foreach($ids as $id) {
			wincache_ucache_delete($id);
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave(array $values, $lifetime) {
		try {
			if(false === $failures = wincache_ucache_set($values, null, $lifetime)) {
				$failures = $values;
			}
			return array_keys($failures);
		} catch(\Throwable $e) {
			if(1 === \count($values)) {
				wincache_ucache_delete(key($values));
			}
			throw $e;
		}
	}

}
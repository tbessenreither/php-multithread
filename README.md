# MultiLevelCache Symfony Bundle

A high-performance, multi-level caching system for Symfony applications. This bundle provides a flexible, extensible, and developer-friendly way to combine multiple cache backends (memory, Redis, file, etc.) for optimal speed and reliability.

---

## Features
- Multi-level cache (memory, Redis, file, etc.)
- Pluggable cache implementations
- Cache statistics and profiling (Symfony Profiler integration)
- Beta decay and TTL randomization to prevent stampedes
- Easy integration with Symfony Dependency Injection
- Extensible via interfaces and factories
- Exception handling and diagnostics

---

## Requirements
- PHP >= 8.4
- Symfony >= 7.4
- Redis (optional, for Redis cache level)

---

## Installation

Install via Composer from GitHub:

1. Add the repository to your `composer.json`:
   ```json
   {
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/tbessenreither/multi-level-cache"
       }
     ]
   }
   ```
2. Require the package:
   ```bash
   composer require tbessenreither/multi-level-cache
   ```
3. Enable the Bundle in `config/bundles.php`:
   ```php
   Tbessenreither\MultiLevelCache\Bundle\MultiLevelCacheBundle::class => ['all' => true],
   ```
4. Configure Environment Variables as needed:
   - `REDIS_DSN` (if using Redis)
   - `MLC_DISABLE_READ` (optional, disables cache reads)
   - `MLC_COLLECT_ENHANCED_DATA` (optional, enables enhanced data collection but has performance impact)

---

## Usage

You can use the multi-level cache in two ways:

### 1. Setup

#### 1.1 Manual Setup in Your Service/Controller

You can instantiate and configure the `MultiLevelCacheService` directly in your constructor, providing the cache implementations you want to use. The Redis client should be injected via dependency injection:

```php
use Tbessenreither\MultiLevelCache\Service\MultiLevelCacheService;
use Tbessenreither\MultiLevelCache\Service\Implementations\InMemoryCacheService;
use Tbessenreither\MultiLevelCache\Service\Implementations\DirectRedisCacheService;
use Redis; // or RedisCluster

public function __construct(Redis $redisClient) {
    $inMemory = new InMemoryCacheService();
    $redis = new DirectRedisCacheService($redisClient);
    $this->cache = new MultiLevelCacheService([
        $inMemory,
        $redis,
    ]);
}
```

#### 1.2 Using the Factory (Recommended for Symfony DI)

Inject the `MultiLevelCacheFactory` and use it to create a pre-configured cache service:

```php
use Tbessenreither\MultiLevelCache\Factory\MultiLevelCacheFactory;

public function __construct(MultiLevelCacheFactory $cacheFactory) {
    $this->cache = $cacheFactory->createDefault2LevelCache();
}
```

### 2. Using the Cache (Identical for Both Approaches)

Once you have a `MultiLevelCacheService` instance (from either method above), usage is identical:

```php
$this->cache->set('my_key', $object, 3600);
$value = $this->cache->get('my_key', function() {
    // This callback is called only if there is a cache miss.
    // Return the value to be cached and returned.
    return $expensiveComputationOrFetch();
}, 3600);
$this->cache->delete('my_key');
```

---

## Architecture Overview

- **Bundle:** Entry point for Symfony integration ([src/Bundle/MultiLevelCacheBundle.php](src/Bundle/MultiLevelCacheBundle.php))
- **Service:** Main cache logic ([src/Service/MultiLevelCacheService.php](src/Service/MultiLevelCacheService.php))
- **Factory:** Helper for common cache setups ([src/Factory/MultiLevelCacheFactory.php](src/Factory/MultiLevelCacheFactory.php))
- **Implementations:**
  - In-memory ([src/Service/Implementations/InMemoryCacheService.php](src/Service/Implementations/InMemoryCacheService.php))
  - Redis ([src/Service/Implementations/DirectRedisCacheService.php](src/Service/Implementations/DirectRedisCacheService.php))
  - File ([src/Service/Implementations/FileCacheService.php](src/Service/Implementations/FileCacheService.php))
- **Key Generator:** ([src/Service/CacheKeyGeneratorService.php](src/Service/CacheKeyGeneratorService.php))
- **Interfaces:**
  - [MultiLevelCacheImplementationInterface](src/Interface/MultiLevelCacheImplementationInterface.php)
  - [CacheInformationInterface](src/Interface/CacheInformationInterface.php)
- **DTOs:** Data transfer objects for cache and profiling ([src/Dto/](src/Dto/))
- **Enums:** Error and warning enums ([src/Enum/](src/Enum/))
- **Exceptions:** Custom exception types ([src/Exception/](src/Exception/))
- **Profiler Integration:** Data collector and templates ([src/DataCollector/](src/DataCollector/), [src/Templates/Profiler/](src/Templates/Profiler/))

---

## Configuration Example

> **Note:** While it is possible to configure the MultiLevelCacheService directly in `services.yaml`, this approach is discouraged. The intended and recommended way is to use the `MultiLevelCacheFactory` for setup and configuration.

```yaml
# config/services.yaml
services:
    Tbessenreither\MultiLevelCache\Service\MultiLevelCacheService:
        arguments:
            $caches:
                - '@Tbessenreither\MultiLevelCache\Service\Implementations\InMemoryCacheService'
                - '@Tbessenreither\MultiLevelCache\Service\Implementations\DirectRedisCacheService'
            $writeL0OnSet: true
            $ttlRandomnessSeconds: 10
            $cacheGroupName: 'default'
```

---

## Testing

Run PHPUnit tests:
```bash
ddev composer test
```

---

## File Overview

- **src/Bundle/**: Symfony bundle integration
- **src/Service/**: Main service and cache implementations
- **src/Factory/**: Factory for cache setup
- **src/Dto/**: Data transfer objects
- **src/Enum/**: Error and warning enums
- **src/Exception/**: Custom exceptions
- **src/Interface/**: Interfaces for extensibility
- **src/DataCollector/**: Profiler and statistics
- **src/Templates/Profiler/**: Symfony profiler templates
- **tests/**: PHPUnit tests

---

## License

MIT

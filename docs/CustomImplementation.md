# Creating Custom Snowflake ID Generators

This guide shows how to create and register custom Snowflake ID generators with Laraflake.

## Option 1: Implement SnowflakeGeneratorInterface Directly

If your generator is simple, you can implement the `SnowflakeGeneratorInterface` directly:

```php
<?php

namespace App\Snowflake;

use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;
use Godruoyi\Snowflake\SequenceResolver;

class CustomGenerator implements SnowflakeGeneratorInterface
{
    private int $nodeId;
    private int $startTime;
    private SequenceResolver $sequenceResolver;
    
    public function __construct(int $nodeId)
    {
        $this->nodeId = $nodeId;
        $this->startTime = strtotime('2024-01-01') * 1000; 
        $this->sequenceResolver = new \Godruoyi\Snowflake\RandomSequenceResolver();
    }
    
    public function id(): string
    {
        // Your custom ID generation logic here
        $timestamp = floor(microtime(true) * 1000) - $this->startTime;
        $sequence = $this->sequenceResolver->sequence($timestamp);
        
        // Example: 41 bits timestamp, 10 bits node ID, 12 bits sequence
        $id = ($timestamp << 22) | ($this->nodeId << 12) | $sequence;
        
        return (string) $id;
    }
    
    public function parseId(string $id, bool $transform = false): array
    {
        $int = (int) $id;
        
        return [
            'timestamp' => ($int >> 22) + $this->startTime,
            'nodeId' => ($int >> 12) & 0x3FF,
            'sequence' => $int & 0xFFF
        ];
    }
    
    public function setStartTimeStamp(int $millisecond): SnowflakeGeneratorInterface
    {
        $this->startTime = $millisecond;
        return $this;
    }
    
    public function setSequenceResolver(SequenceResolver|\Closure $sequence): SnowflakeGeneratorInterface
    {
        if ($sequence instanceof \Closure) {
            $sequence = new class($sequence) implements SequenceResolver {
                private $closure;
                
                public function __construct(\Closure $closure)
                {
                    $this->closure = $closure;
                }
                
                public function sequence(int $currentTime): int
                {
                    return call_user_func($this->closure, $currentTime);
                }
            };
        }
        
        $this->sequenceResolver = $sequence;
        return $this;
    }
}
```

Then register this in your `config/laraflake.php`:

```php
'snowflake_type' => \App\Snowflake\CustomGenerator::class,
```

## Option 2: Use the Factory Pattern (Recommended)

For more complex generators or when you need additional dependencies, implement the factory interface:

```php
<?php

namespace App\Snowflake;

use CalebDW\Laraflake\Contracts\SnowflakeGeneratorFactoryInterface;
use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;

class CustomGeneratorFactory implements SnowflakeGeneratorFactoryInterface
{
    private $redis;
    
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }
    
    public function create(array $config): SnowflakeGeneratorInterface
    {
        // Access any custom options from the config
        $customNodeId = $config['custom_options']['node_id'] ?? 1;
        
        // Create your custom generator with dependencies
        $generator = new CustomGenerator($customNodeId);
        
        // Set additional properties from config
        if (isset($config['epoch'])) {
            $generator->setStartTimeStamp(strtotime($config['epoch']) * 1000);
        }
        
        // Set a custom sequence resolver that uses Redis
        $generator->setSequenceResolver(function ($timestamp) {
            return $this->redis->incr('snowflake:' . $timestamp) % 4096;
        });
        
        return $generator;
    }
}
```

Then register this in your `config/laraflake.php`:

```php
'generator_factory' => \App\Snowflake\CustomGeneratorFactory::class,
'custom_options' => [
    'node_id' => env('CUSTOM_NODE_ID', 1),
    // Any other options your generator needs
],
```

## Option 3: Use a Closure Factory

For simpler cases, you can use a closure in a service provider:

```php
<?php

namespace App\Providers;

use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;
use App\Snowflake\CustomGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Update the config with a closure factory
        config()->set('laraflake.generator_factory', function ($config) {
            $generator = new CustomGenerator($config['custom_options']['node_id'] ?? 1);
            
            // Additional setup here
            
            return $generator;
        });
    }
}
```

## Testing

When testing, you can easily mock the generator:

```php
<?php

use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;

public function testWithMockedSnowflake()
{
    // Create a mock that always returns a known ID
    $mock = $this->mock(SnowflakeGeneratorInterface::class);
    $mock->shouldReceive('id')->andReturn('123456789');
    
    // Your test here
    $result = $this->post('/api/users');
    $result->assertJson(['id' => '123456789']);
}
``` 
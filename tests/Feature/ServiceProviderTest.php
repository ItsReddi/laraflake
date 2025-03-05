<?php

declare(strict_types=1);

use CalebDW\Laraflake\Adapters\GodruoyiSnowflakeAdapter;
use CalebDW\Laraflake\Adapters\GodruoyiSonyflakeAdapter;
use CalebDW\Laraflake\Contracts\SnowflakeGeneratorFactoryInterface;
use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;
use Godruoyi\Snowflake\LaravelSequenceResolver;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Snowflake;
use Godruoyi\Snowflake\Sonyflake;
use Illuminate\Contracts\Cache\Repository;

class ServiceProviderTestCustomGenerator implements SnowflakeGeneratorInterface
{
    private int $nodeId;

    public function __construct(int $nodeId = 1)
    {
        $this->nodeId = $nodeId;
    }

    public function id(): string
    {
        return '987654321';
    }

    public function parseId(string $id, bool $transform = false): array
    {
        return ['test' => $id, 'transform' => $transform];
    }

    public function setStartTimeStamp(int $millisecond): SnowflakeGeneratorInterface
    {
        return $this;
    }

    public function setSequenceResolver(SequenceResolver|Closure $sequence): SnowflakeGeneratorInterface
    {
        return $this;
    }
}

class ServiceProviderTestCustomFactory implements SnowflakeGeneratorFactoryInterface
{
    public function create(array $config): SnowflakeGeneratorInterface
    {
        return new ServiceProviderTestCustomGenerator(
            $config['custom_options']['node_id'] ?? 1,
        );
    }
}

it('binds random sequence resolver when there is not a cache', function () {
    app()->offsetUnset('cache.store');
    app()->forgetInstance(SequenceResolver::class);

    expect(app()->make(SequenceResolver::class))
        ->toBeInstanceOf(RandomSequenceResolver::class);
});

it('binds laravel sequence resolver when there is a cache', function () {
    app()->instance('cache.store', mock(Repository::class));
    app()->forgetInstance(SequenceResolver::class);

    expect(app()->make(SequenceResolver::class))
        ->toBeInstanceOf(LaravelSequenceResolver::class);
});

it('registers the AboutCommand entry', function () {
    test()->artisan('about')
        ->assertSuccessful()
        ->expectsOutputToContain('Laraflake');
});

it('supports Snowflakes', function () {
    config()->set('laraflake.snowflake_type', Snowflake::class);

    expect(app()->make(SnowflakeGeneratorInterface::class))
        ->toBeInstanceOf(GodruoyiSnowflakeAdapter::class);

    test()->artisan('about')
        ->assertSuccessful()
        ->expectsOutputToContain('Snowflake');
});

it('supports Sonyflakes', function () {
    config()->set('laraflake.snowflake_type', Sonyflake::class);

    expect(app()->make(SnowflakeGeneratorInterface::class))
        ->toBeInstanceOf(GodruoyiSonyflakeAdapter::class);

    test()->artisan('about')
        ->assertSuccessful()
        ->expectsOutputToContain('Sonyflake');
});

it('throws exception for invalid snowflake type', function () {
    config()->set('laraflake.snowflake_type', 'invalid');

    test()->artisan('about')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Sonyflake');

    expect(app()->make(SnowflakeGeneratorInterface::class))
        ->toBeInstanceOf(GodruoyiSonyflakeAdapter::class);
})->throws(InvalidArgumentException::class);

it('supports custom generator implementation via snowflake_type', function () {
    app()->bind(ServiceProviderTestCustomGenerator::class, function () {
        return new ServiceProviderTestCustomGenerator(5);
    });

    config()->set('laraflake.snowflake_type', ServiceProviderTestCustomGenerator::class);

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    $generator = app()->make(SnowflakeGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(ServiceProviderTestCustomGenerator::class);
    expect($generator->id())->toBe('987654321');
});

it('supports custom generator factory via generator_factory', function () {
    config()->set('laraflake.generator_factory', ServiceProviderTestCustomFactory::class);
    config()->set('laraflake.custom_options', ['node_id' => 10]);

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    $generator = app()->make(SnowflakeGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(ServiceProviderTestCustomGenerator::class);
    expect($generator->id())->toBe('987654321');
});

it('supports closure factory via generator_factory', function () {
    config()->set('laraflake.generator_factory', function ($config) {
        return new ServiceProviderTestCustomGenerator($config['custom_options']['node_id'] ?? 1);
    });
    config()->set('laraflake.custom_options', ['node_id' => 15]);

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    $generator = app()->make(SnowflakeGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(ServiceProviderTestCustomGenerator::class);
    expect($generator->id())->toBe('987654321');
});

it('throws exception for invalid factory', function () {
    config()->set('laraflake.generator_factory', 'NotAClass');

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    app()->make(SnowflakeGeneratorInterface::class);
})->throws(InvalidArgumentException::class);

it('throws exception when factory returns wrong type', function () {
    config()->set('laraflake.generator_factory', function () {
        return new stdClass();
    });

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    app()->make(SnowflakeGeneratorInterface::class);
})->throws(InvalidArgumentException::class);

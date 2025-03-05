<?php

declare(strict_types=1);

use CalebDW\Laraflake\Contracts\SnowflakeGeneratorFactoryInterface;
use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;
use Godruoyi\Snowflake\SequenceResolver;

class SimpleCustomGenerator implements SnowflakeGeneratorInterface
{
    private int $nodeId;

    public function __construct(int $nodeId)
    {
        $this->nodeId = $nodeId;
    }

    public function id(): string
    {
        return '123456789';
    }

    public function parseId(string $id, bool $transform = false): array
    {
        return [
            'test'      => $id,
            'transform' => $transform,
        ];
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

class CustomGeneratorFactory implements SnowflakeGeneratorFactoryInterface
{
    public function create(array $config): SnowflakeGeneratorInterface
    {
        return new SimpleCustomGenerator($config['custom_options']['node_id'] ?? 1);
    }
}

it('supports direct custom generator implementation', function () {
    config()->set('laraflake.snowflake_type', SimpleCustomGenerator::class);
    config()->set('laraflake.custom_options', ['node_id' => 5]);

    app()->bind(SimpleCustomGenerator::class, function ($app, $params) {
        $config = config('laraflake');

        return new SimpleCustomGenerator($config['custom_options']['node_id'] ?? 1);
    });

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    $generator = app()->make(SnowflakeGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(SimpleCustomGenerator::class);
    expect($generator->id())->toBe('123456789');
});

it('supports custom generator factory', function () {
    config()->set('laraflake.generator_factory', CustomGeneratorFactory::class);
    config()->set('laraflake.custom_options', ['node_id' => 5]);

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    $generator = app()->make(SnowflakeGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(SimpleCustomGenerator::class);
    expect($generator->id())->toBe('123456789');
});

it('supports closure factory', function () {
    config()->set('laraflake.generator_factory', function ($config) {
        return new SimpleCustomGenerator($config['custom_options']['node_id'] ?? 1);
    });
    config()->set('laraflake.custom_options', ['node_id' => 5]);

    app()->forgetInstance(SnowflakeGeneratorInterface::class);

    $generator = app()->make(SnowflakeGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(SimpleCustomGenerator::class);
    expect($generator->id())->toBe('123456789');
});

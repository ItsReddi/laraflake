<?php

declare(strict_types=1);

namespace CalebDW\Laraflake;

use CalebDW\Laraflake\Adapters\GodruoyiSnowflakeAdapter;
use CalebDW\Laraflake\Adapters\GodruoyiSonyflakeAdapter;
use CalebDW\Laraflake\Contracts\SnowflakeGeneratorFactoryInterface;
use CalebDW\Laraflake\Contracts\SnowflakeGeneratorInterface;
use CalebDW\Laraflake\Macros\BlueprintMacros;
use CalebDW\Laraflake\Macros\RuleMacros;
use CalebDW\Laraflake\Macros\StrMacros;
use Closure;
use Composer\InstalledVersions;
use Godruoyi\Snowflake\FileLockResolver;
use Godruoyi\Snowflake\LaravelSequenceResolver;
use Godruoyi\Snowflake\PredisSequenceResolver;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\RedisSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Snowflake;
use Godruoyi\Snowflake\Sonyflake;
use Godruoyi\Snowflake\SwooleSequenceResolver;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use InvalidArgumentException;
use ReflectionClass;

/**
 * @phpstan-type LaraflakeConfig array{
 *     datacenter_id: int,
 *     worker_id: int,
 *     epoch: string,
 *     machine_id: int,
 *     snowflake_type: class-string<Snowflake>,
 *     generator_factory: class-string<SnowflakeGeneratorFactoryInterface>|null|Closure,
 *     custom_options: array<string, mixed>
 * }
 */
class ServiceProvider extends IlluminateServiceProvider
{
    /** @inheritDoc */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laraflake.php', 'laraflake');

        $this->registerSequenceResolver();
        $this->registerSnowflake();
    }

    /** @inheritDoc */
    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/laraflake.php' => config_path('laraflake.php')]);

        $this->registerMacros();

        AboutCommand::add('Laraflake', function () {
            /** @var LaraflakeConfig $config */
            $config = config('laraflake');

            return [
                'Snowflake Type' => $config['snowflake_type'],
                ...match ($config['snowflake_type']) {
                    Snowflake::class => [
                        'Datacenter ID' => $config['datacenter_id'],
                        'Worker ID'     => $config['worker_id'],
                    ],
                    Sonyflake::class => [
                        'Machine ID' => $config['machine_id'],
                    ],
                    default => [],
                },
                'Epoch'             => $config['epoch'],
                'Sequence Resolver' => $this->getPrettyResolver(),
                'Version'           => InstalledVersions::getPrettyVersion('calebdw/laraflake'),
            ];
        });
    }

    /** Register custom mixins. */
    protected function registerMacros(): void
    {
        BlueprintMacros::boot();
        RuleMacros::boot();
        StrMacros::boot();
    }

    /** Register the Snowflake singleton. */
    protected function registerSnowflake(): void
    {
        $this->app->singleton(SnowflakeGeneratorInterface::class, function (Application $app) {
            /** @var LaraflakeConfig $config */
            $config = config('laraflake');

            if (! empty($config['generator_factory'])) {
                return $this->createFromCustomFactory($config);
            }

            return (match ($config['snowflake_type']) {
                Snowflake::class => new GodruoyiSnowflakeAdapter($config['datacenter_id'], $config['worker_id']),
                Sonyflake::class => new GodruoyiSonyflakeAdapter($config['machine_id']),
                default          => $this->resolveCustomGenerator($config),
            })->setStartTimeStamp(strtotime($config['epoch']) * 1000)
                ->setSequenceResolver($app->make(SequenceResolver::class));
        });

        $this->app->alias(SnowflakeGeneratorInterface::class, 'laraflake');
    }

    /** @param LaraflakeConfig $config */
    protected function createFromCustomFactory(array $config): SnowflakeGeneratorInterface
    {
        $factory = $config['generator_factory'];

        if ($factory instanceof Closure) {
            $generator = $factory($config);
        } elseif (is_string($factory) && class_exists($factory)) {
            $factoryInstance = $this->app->make($factory);

            if (! $factoryInstance instanceof SnowflakeGeneratorFactoryInterface) {
                throw new InvalidArgumentException(
                    'Custom generator factory class must implement SnowflakeGeneratorFactoryInterface',
                );
            }

            $generator = $factoryInstance->create($config);
        } else {
            throw new InvalidArgumentException("Invalid generator factory specified: {$factory}");
        }

        if (! $generator instanceof SnowflakeGeneratorInterface) {
            throw new InvalidArgumentException(
                'Custom generator factory must return an instance of SnowflakeGeneratorInterface',
            );
        }

        return $generator;
    }

    /** @param LaraflakeConfig $config */
    protected function resolveCustomGenerator(array $config): SnowflakeGeneratorInterface
    {
        $type = $config['snowflake_type'];

        if (class_exists($type)) {
            $reflection = new ReflectionClass($type);

            if ($reflection->implementsInterface(SnowflakeGeneratorInterface::class)) {
                $customGenerator = $this->app->make($type);

                return $customGenerator instanceof SnowflakeGeneratorInterface ? $customGenerator : throw new InvalidArgumentException('Custom generator must implement SnowflakeGeneratorInterface');
            }
        }

        throw new InvalidArgumentException("Invalid Snowflake type: {$type}");
    }

    /** Bind the Snowflake sequence resolver. */
    protected function registerSequenceResolver(): void
    {
        $this->app->bind(SequenceResolver::class, function (Application $app) {
            if (! $app->has('cache.store')) {
                return new RandomSequenceResolver();
            }

            $repository = $app->get('cache.store');
            assert($repository instanceof Repository);

            return new LaravelSequenceResolver($repository);
        });
    }

    /** @codeCoverageIgnore */
    protected function getPrettyResolver(): string
    {
        /** @var SequenceResolver $resolver */
        $resolver = $this->app->make(SequenceResolver::class);

        return match ($resolver::class) {
            LaravelSequenceResolver::class => 'Laravel Cache',
            RandomSequenceResolver::class  => 'Random (unsafe)',
            SwooleSequenceResolver::class  => 'Swoole',
            RedisSequenceResolver::class   => 'Redis',
            FileLockResolver::class        => 'File',
            PredisSequenceResolver::class  => 'Predis',
            default                        => $resolver::class,
        };
    }
}

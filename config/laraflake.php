<?php

declare(strict_types=1);

use Godruoyi\Snowflake\Snowflake;

return [
    /*
    |--------------------------------------------------------------------------
    | Snowflake Type
    |--------------------------------------------------------------------------
    |
    | The class to use by Laraflake when generating unique identifiers.
    | The default is the Snowflake class, but you can also use the Sonyflake class.
    | You can also provide your own custom implementation class name here,
    | as long as it implements the SnowflakeGeneratorInterface.
    */
    'snowflake_type' => Snowflake::class,

    /*
    |--------------------------------------------------------------------------
    | Custom Generator Factory
    |--------------------------------------------------------------------------
    |
    | If you're using a custom generator (not Snowflake or Sonyflake), you can
    | specify a factory class or closure that will be used to instantiate your
    | generator. This factory must return an object that implements the
    | SnowflakeGeneratorInterface.
    |
    | This can be a class name that implements the factory pattern or a closure.
    | Leave as null to use the default factory logic based on snowflake_type.
    */
    'generator_factory' => null,

    /*
    |--------------------------------------------------------------------------
    | Snowflake Epoch
    |--------------------------------------------------------------------------
    |
    | This value represents the starting date (epoch) for generating new timestamps.
    | Snowflakes can be created for 69 years past this date. In most cases,
    | you should set this value to the current date when building a new app.
    |
    */
    'epoch' => env('LARAFLAKE_EPOCH', '2024-01-01'),

    /*
    |--------------------------------------------------------------------------
    | Snowflake Data Center & Worker IDs
    |--------------------------------------------------------------------------
    |
    | These values represents the data center and worker ids that should be used
    | by Snowflake when generating unique identifiers. The values must be 0--31.
    |
    */
    'datacenter_id' => env('LARAFLAKE_DATACENTER_ID', 0),
    'worker_id'     => env('LARAFLAKE_WORKER_ID', 0),

    /*
    |--------------------------------------------------------------------------
    | Sonyflake Machine ID
    |--------------------------------------------------------------------------
    |
    | This value represents the machine id that should be used by Sonyflake when
    | generating unique identifiers. The value must be 0--65535 (2^16 - 1).
    |
    */
    'machine_id' => env('LARAFLAKE_MACHINE_ID', 0),

    /*
    |--------------------------------------------------------------------------
    | Custom Generator Options
    |--------------------------------------------------------------------------
    |
    | Additional options that will be passed to your custom generator factory.
    | This allows you to provide configuration specific to your implementation
    | without modifying the core Laraflake package.
    */
    'custom_options' => [],
];

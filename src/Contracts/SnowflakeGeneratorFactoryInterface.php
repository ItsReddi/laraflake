<?php

declare(strict_types=1);

namespace CalebDW\Laraflake\Contracts;

interface SnowflakeGeneratorFactoryInterface
{
    /** @param array<string, mixed> $config The configuration options from the laraflake config file */
    public function create(array $config): SnowflakeGeneratorInterface;
}

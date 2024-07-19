<?php

declare(strict_types=1);

dataset('snowflakes', [
    ['5467898741564654', true],
    [5467898741564654, true],
    [5467898741564654.0, false],
    [5467898741564654.5, false],
    ['9223372036854775807', true],
    ['-1', false],
    [-1, false],
    ['9223372036854775808', false],
    ['9223372036854775807.54', false],
    ['7987987979223372036854775808', false],
    ['invalid', false],
]);

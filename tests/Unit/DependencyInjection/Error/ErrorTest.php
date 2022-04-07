<?php

namespace App\Tests\Unit\DependencyInjection\Error;

use App\DependencyInjection\Error\Error;
use App\DependencyInjection\Error\Issue;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    public function testError()
    {
        $issue = new Issue('Test issue', 'Test location');
        $error = new Error('Test message', '40001', [$issue]);

        self::assertSame('Test message', $error->getErrorMessage());
        self::assertSame('40001', $error->getErrorCode());
        self::assertCount(1, $error->getIssues());
        self::assertSame($issue, $error->getIssues()[0]);
    }
}

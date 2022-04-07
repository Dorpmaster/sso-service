<?php

namespace App\Tests\Unit\DependencyInjection\Exception;

use App\DependencyInjection\Error\Issue;
use App\DependencyInjection\Exception\ApiException;
use PHPUnit\Framework\TestCase;

class ApiExceptionTest extends TestCase
{
    public function testException()
    {
        $issue = new Issue('Test issue', 'Test location');

        $exception = new ApiException(400);
        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('', $exception->getMessage());
        self::assertNull($exception->getErrorCode());
        self::assertIsArray($exception->getIssues());
        self::assertCount(0, $exception->getIssues());

        $exception = new ApiException(400, 'Test message', '4001', [$issue]);
        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('Test message', $exception->getMessage());
        self::assertSame('4001', $exception->getErrorCode());
        self::assertIsArray($exception->getIssues());
        self::assertCount(1, $exception->getIssues());
        self::assertSame($issue, $exception->getIssues()[0]);
    }
}

<?php

namespace App\Tests\Unit\DependencyInjection\Http;

use App\DependencyInjection\Error\Issue;
use App\DependencyInjection\Http\ApiExceptionResponse;
use PHPUnit\Framework\TestCase;

class ApiExceptionResponseTest extends TestCase
{
    public function testResponse()
    {
        $issue = new Issue('Test issue', 'Test Location');

        $response = new ApiExceptionResponse();
        self::assertSame(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertIsArray($content);
        self::assertArrayHasKey('errorCode', $content);
        self::assertNull($content['errorCode']);
        self::assertArrayHasKey('errorMessage', $content);
        self::assertNull($content['errorMessage']);
        self::assertArrayHasKey('issues', $content);
        self::assertIsArray($content['issues']);
        self::assertCount(0, $content['issues']);

        $response = new ApiExceptionResponse(400, 'Invalid data', '4001', [$issue]);
        self::assertSame(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertIsArray($content);
        self::assertArrayHasKey('errorCode', $content);
        self::assertSame('4001', $content['errorCode']);
        self::assertArrayHasKey('errorMessage', $content);
        self::assertSame('Invalid data', $content['errorMessage']);
        self::assertArrayHasKey('issues', $content);
        self::assertIsArray($content['issues']);
        self::assertCount(1, $content['issues']);
        $issues = $content['issues'];
        self::assertSame('Test issue', $issues[0]['issue']);
        self::assertSame('Test Location', $issues[0]['location']);
    }
}

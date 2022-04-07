<?php

namespace App\Tests\Unit\DependencyInjection\Error;

use App\DependencyInjection\Error\Issue;
use PHPUnit\Framework\TestCase;

class IssueTest extends TestCase
{
    public function testIssue()
    {
        $issue = new Issue('Test issue');
        self::assertSame('Test issue', $issue->getIssue());
        self::assertNull($issue->getLocation());

        $issue = new Issue('Test issue', 'Test Location');
        self::assertSame('Test issue', $issue->getIssue());
        self::assertSame('Test Location', $issue->getLocation());
    }
}

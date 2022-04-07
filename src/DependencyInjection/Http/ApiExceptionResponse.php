<?php

namespace App\DependencyInjection\Http;

use App\Domain\Error\IssueInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiExceptionResponse extends JsonResponse
{
    public function __construct(
        int $status = 500,
        ?string $errorMessage = null,
        ?string $errorCode = null,
        array $issues = [],
        array $headers = [],
    ) {
        $data = [
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage,
            'issues' => [],
        ];

        foreach ($issues as $issue) {
            if (!$issue instanceof IssueInterface) {
                continue;
            }

            $data['issues'][] = [
                'issue' => $issue->getIssue(),
                'location' => $issue->getLocation(),
            ];
        }

        parent::__construct($data, $status, $headers);
    }

}

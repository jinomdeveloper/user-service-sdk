<?php

namespace Jinom\UserServiceSdk\Exceptions;

use Exception;

class UserServiceException extends Exception
{
    public function __construct(
        string $message = 'User Service request failed',
        int $code = 0,
        ?Exception $previous = null,
        public ?string $responseBody = null,
        public ?array $responseData = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for HTTP error response
     */
    public static function httpError(int $statusCode, string $responseBody): self
    {
        $data = json_decode($responseBody, true);
        $message = $data['message'] ?? $data['error'] ?? "HTTP Error {$statusCode}";

        return new self(
            message: "User Service error: {$message}",
            code: $statusCode,
            responseBody: $responseBody,
            responseData: $data
        );
    }

    /**
     * Create exception for connection error
     */
    public static function connectionError(string $error): self
    {
        return new self(
            message: "Failed to connect to User Service: {$error}",
            code: 0
        );
    }

    /**
     * Create exception for missing token
     */
    public static function noToken(): self
    {
        return new self(
            message: 'No valid Keycloak token available for User Service request',
            code: 401
        );
    }

    /**
     * Create exception for user not found
     */
    public static function userNotFound(string $identifier): self
    {
        return new self(
            message: "User not found: {$identifier}",
            code: 404
        );
    }
}

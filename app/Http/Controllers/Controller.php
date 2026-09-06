<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    /**
     * Normalizes an exception code into a valid HTTP status code.
     *
     * Exception codes are not guaranteed to be valid HTTP statuses: PDO
     * exceptions use SQLSTATE strings (e.g. "HY000", "23000") and many
     * exceptions use 0. This guarantees an int within the 100..599 range,
     * falling back to 500 otherwise.
     */
    protected function normalizeStatusCode(mixed $code, int $fallback = Response::HTTP_INTERNAL_SERVER_ERROR): int
    {
        if (!is_int($code) && !(is_string($code) && ctype_digit($code))) {
            return $fallback;
        }

        $code = (int) $code;

        return ($code >= 100 && $code <= 599) ? $code : $fallback;
    }
}

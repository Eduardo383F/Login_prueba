<?php

namespace App\Support;

class ApiResponse
{
    public static function success(string $message, array $data = [], int $code = 200)
    {
        // agrega el response_code dentro de data
        $data['response_code'] = $code;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    public static function error(string $message, array $error = [], int $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => array_merge($error, ['http_code' => $code]),
        ], $code);
    }
}

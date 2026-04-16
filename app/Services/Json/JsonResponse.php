<?php

namespace App\Services\Json;

class JsonResponse
{
    public static function success($message, $data = null)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => $message ?? 'Success',
            'data' => $data,
        ], 200);
    }

    public static function created($message, $data = null)
    {
        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => $message ?? 'Created',
            'data' => $data,
        ], 201);
    }

    public static function bad_request($message, $data = null)
    {
        return response()->json([
            'status' => 400,
            'success' => false,
            'message' => $message ?? 'Bad Request',
            'data' => $data,
        ], 400);
    }

    public static function unauthorized($message, $data = null)
    {
        return response()->json([
            'status' => 401,
            'success' => false,
            'message' => $message ?? 'Unauthorized',
            'data' => $data,
        ], 401);
    }

    public static function forbidden($message, $data = null)
    {
        return response()->json([
            'status' => 403,
            'success' => false,
            'message' => $message ?? 'Forbidden',
            'data' => $data,
        ], 403);
    }

    public static function not_found($message, $data = null)
    {
        return response()->json([
            'status' => 404,
            'success' => false,
            'message' => $message ?? 'Not Found',
            'data' => $data,
        ], 404);
    }

    public static function conflict($message, $data = null)
    {
        return response()->json([
            'status' => 409,
            'success' => false,
            'message' => $message ?? '',
            'data' => $data,
        ], 409);
    }

    public static function unprocessable_entity($message, $data = null)
    {
        return response()->json([
            'status' => 422,
            'success' => false,
            'message' => $message ?? 'Unprocessable Entity',
            'data' => $data,
        ], 422);
    }

    public static function internal_server_error($message, $data = null)
    {
        return response()->json([
            'status' => 500,
            'success' => false,
            'message' => $message ?? 'Internal Server Error',
            'data' => $data,
        ], 500);
    }
}

?>

<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function send(string $message = '', int $code = 200, mixed $result = []): JsonResponse
    {
        $response = [];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if (!empty($result)) {
            $response['data'] = $result;
        }

        return response()->json($response, $code);
    }

    public static function throw(mixed $errors = [], string $message = 'Something went wrong', int $code = 422): JsonResponse
    {
        
        $response = [
            'message' => $message,
            'errors' => $errors,
        ];

        Log::error('API Exception', [
            'status' => $code,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toDateTimeString(),
        ]);

        throw new HttpResponseException(response()->json($response, $code));

    }

    /**
     * Paginated response with resource transformation
     */
    public static function paginated(string $message, LengthAwarePaginator $paginator, ?string $resourceClass = null): JsonResponse
    {
        // Transform data using resource class if provided
        $data = $resourceClass 
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        $response = [
            'message' => $message,
            'data' => $data,
            'pagination' => [   
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'first_page_url' => $paginator->url(1),
                'last_page_url' => $paginator->url($paginator->lastPage()),
            ]
        ];

        return response()->json($response, 200);
    }
    
    public static function index(string $message = '', mixed $result = []): JsonResponse
    {
        return self::send($message, 200, $result);
    }

    public static function show(string $message = 'Data', mixed $result = []): JsonResponse
    {
        return self::send($message, 200, $result);
    }

    public static function store(string $message = 'Created successfully', mixed $result = []): JsonResponse
    {
        return self::send($message, 201, $result);
    }

    public static function update(string $message = 'Updated successfully', mixed $result = []): JsonResponse
    {
        return self::send($message, 200, $result);
    }

    public static function delete(string $message = 'Deleted successfully'): JsonResponse
    {
        return self::send($message, 204);
    }

    public static function notFound(string $message = 'Not found!'): JsonResponse
    {
        return self::send($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::send($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::send($message, 403);
    }

    public static function serverError(string $message = 'Server error'): JsonResponse
    {
        return self::send($message, 500);
    }

    public static function failValidation(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::throw($errors, $message, 422);
    }

    public static function successMessage(string $message = 'Success'): JsonResponse
    {
        return self::send($message, 200);
    }

    public static function customError(string $message = '', int $code = 200, mixed $data = []): JsonResponse
    {
        return self::send($message, $code, $data);
    }

    public static function custom(string $message = '', int $code = 200, mixed $data = []): JsonResponse
    {
        return self::send($message, $code, $data);
    }
}
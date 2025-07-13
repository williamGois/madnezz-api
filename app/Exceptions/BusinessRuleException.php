<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BusinessRuleException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'type' => 'business_rule_violation'
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
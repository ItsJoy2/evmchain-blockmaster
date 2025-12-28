<?php

namespace App\Exceptions;

use Exception;

class RpcException extends Exception
{
    protected $details;

    public function __construct($message, $details = [], $code = 0)
    {
        parent::__construct($message, $code);
        $this->details = $details;
    }

    public function render($request)
    {
        return response()->json([
            'status' => false,
            'message' => $this->getMessage(),
            'details' => $this->details,
        ], 422);
    }
}


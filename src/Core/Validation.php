<?php

namespace App\Core;

use App\Core\Request;

class Validation
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    /**
     * Validate request data
     */
    public function validate(Request $request, array $rules, array $messages = []): array
    {
        $data = $request->all();

        if (!$this->validator->validate($data, $rules, $messages)) {
            return [
                'valid' => false,
                'errors' => $this->validator->errors(),
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Validate array data directly
     */
    public function validateArray(array $data, array $rules, array $messages = []): array
    {
        if (!$this->validator->validate($data, $rules, $messages)) {
            return [
                'valid' => false,
                'errors' => $this->validator->errors(),
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Get validator instance for advanced usage
     */
    public function validator(): Validator
    {
        return $this->validator;
    }
}

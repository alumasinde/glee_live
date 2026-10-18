<?php

namespace App\Core;

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $customMessages = [];

    public function validate(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->customMessages = $customMessages;
        $this->errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleset = explode('|', $rule);

            foreach ($ruleset as $r) {
                if (str_contains($r, ':')) {
                    [$ruleName, $parameter] = explode(':', $r, 2);
                    $this->validateRule($field, $value, $ruleName, $parameter);
                } else {
                    $this->validateRule($field, $value, $r);
                }
            }
        }

        return empty($this->errors);
    }

    private function validateRule(string $field, $value, string $rule, $parameter = null)
    {
        $fieldLabel = $this->getFieldLabel($field);

        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, $rule, "{$fieldLabel} is required");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a valid email address");
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < (int)$parameter) {
                    $this->addError($field, $rule, "{$fieldLabel} must be at least {$parameter} characters");
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > (int)$parameter) {
                    $this->addError($field, $rule, "{$fieldLabel} must not exceed {$parameter} characters");
                }
                break;

            case 'between':
                if (!empty($value)) {
                    [$min, $max] = explode(',', $parameter);
                    $len = strlen($value);
                    if ($len < (int)$min || $len > (int)$max) {
                        $this->addError($field, $rule, "{$fieldLabel} must be between {$min} and {$max} characters");
                    }
                }
                break;

            case 'confirmed':
                $confirmationField = $field . '_confirmation';
                if (!isset($this->data[$confirmationField]) || $value !== $this->data[$confirmationField]) {
                    $this->addError($field, $rule, "{$fieldLabel} confirmation does not match");
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($parameter, $value)) {
                    $this->addError($field, $rule, "{$fieldLabel} format is invalid");
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a valid URL");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be numeric");
                }
                break;

            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be an integer");
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, $rule, "{$fieldLabel} must contain only letters");
                }
                break;

            case 'alpha_num':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, $rule, "{$fieldLabel} must contain only letters and numbers");
                }
                break;

            case 'alpha_dash':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    $this->addError($field, $rule, "{$fieldLabel} must contain only letters, numbers, dashes, and underscores");
                }
                break;

            case 'in':
                if (!empty($value)) {
                    $allowed = explode(',', $parameter);
                    if (!in_array($value, $allowed)) {
                        $this->addError($field, $rule, "{$fieldLabel} must be one of: " . implode(', ', $allowed));
                    }
                }
                break;

            case 'not_in':
                if (!empty($value)) {
                    $forbidden = explode(',', $parameter);
                    if (in_array($value, $forbidden)) {
                        $this->addError($field, $rule, "{$fieldLabel} is not allowed");
                    }
                }
                break;

            case 'same':
                if (!empty($value) && $value !== ($this->data[$parameter] ?? null)) {
                    $this->addError($field, $rule, "{$fieldLabel} must match {$parameter}");
                }
                break;

            case 'different':
                if (!empty($value) && $value === ($this->data[$parameter] ?? null)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be different from {$parameter}");
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a valid date");
                }
                break;

            case 'date_format':
                if (!empty($value)) {
                    $date = \DateTime::createFromFormat($parameter, $value);
                    if (!$date || $date->format($parameter) !== $value) {
                        $this->addError($field, $rule, "{$fieldLabel} must match format {$parameter}");
                    }
                }
                break;

            case 'before':
                if (!empty($value) && !empty($this->data[$parameter])) {
                    $date = strtotime($value);
                    $compare = strtotime($this->data[$parameter]);
                    if ($date >= $compare) {
                        $this->addError($field, $rule, "{$fieldLabel} must be before {$parameter}");
                    }
                }
                break;

            case 'after':
                if (!empty($value) && !empty($this->data[$parameter])) {
                    $date = strtotime($value);
                    $compare = strtotime($this->data[$parameter]);
                    if ($date <= $compare) {
                        $this->addError($field, $rule, "{$fieldLabel} must be after {$parameter}");
                    }
                }
                break;

            case 'starts_with':
                if (!empty($value) && !str_starts_with($value, $parameter)) {
                    $this->addError($field, $rule, "{$fieldLabel} must start with {$parameter}");
                }
                break;

            case 'ends_with':
                if (!empty($value) && !str_ends_with($value, $parameter)) {
                    $this->addError($field, $rule, "{$fieldLabel} must end with {$parameter}");
                }
                break;

            case 'ipv4':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a valid IPv4 address");
                }
                break;

            case 'ipv6':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a valid IPv6 address");
                }
                break;

            case 'ip':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a valid IP address");
                }
                break;

            case 'json':
                if (!empty($value) && json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->addError($field, $rule, "{$fieldLabel} must be valid JSON");
                }
                break;

            case 'boolean':
                if (!empty($value) && !in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a boolean");
                }
                break;

            case 'array':
                if (!empty($value) && !is_array($value)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be an array");
                }
                break;

            case 'mimes':
                if (!empty($value) && is_uploaded_file($value['tmp_name'] ?? null)) {
                    $allowed = explode(',', $parameter);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $value['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mimeType, $allowed)) {
                        $this->addError($field, $rule, "{$fieldLabel} must be one of: " . implode(', ', $allowed));
                    }
                }
                break;

            case 'image':
                if (!empty($value) && is_uploaded_file($value['tmp_name'] ?? null)) {
                    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $value['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mimeType, $allowed)) {
                        $this->addError($field, $rule, "{$fieldLabel} must be an image");
                    }
                }
                break;

            case 'file':
                if (!empty($value) && !is_uploaded_file($value['tmp_name'] ?? null)) {
                    $this->addError($field, $rule, "{$fieldLabel} must be a file");
                }
                break;

            case 'size':
                if (!empty($value) && is_uploaded_file($value['tmp_name'] ?? null)) {
                    $maxSize = (int)$parameter * 1024; // Convert KB to bytes
                    if ($value['size'] > $maxSize) {
                        $this->addError($field, $rule, "{$fieldLabel} must not exceed {$parameter} KB");
                    }
                }
                break;

            case 'dimensions':
                if (!empty($value) && is_uploaded_file($value['tmp_name'] ?? null)) {
                    $info = getimagesize($value['tmp_name']);
                    if ($info) {
                        [$width, $height] = $info;
                        [$minWidth, $maxWidth, $minHeight, $maxHeight] = explode(',', $parameter);

                        if ($minWidth && $width < (int)$minWidth) {
                            $this->addError($field, $rule, "{$fieldLabel} width must be at least {$minWidth}px");
                        }
                        if ($maxWidth && $width > (int)$maxWidth) {
                            $this->addError($field, $rule, "{$fieldLabel} width must not exceed {$maxWidth}px");
                        }
                        if ($minHeight && $height < (int)$minHeight) {
                            $this->addError($field, $rule, "{$fieldLabel} height must be at least {$minHeight}px");
                        }
                        if ($maxHeight && $height > (int)$maxHeight) {
                            $this->addError($field, $rule, "{$fieldLabel} height must not exceed {$maxHeight}px");
                        }
                    }
                }
                break;
        }
    }

    private function addError(string $field, string $rule, string $message)
    {
        $key = "{$field}.{$rule}";
        if (isset($this->customMessages[$key])) {
            $message = $this->customMessages[$key];
        } elseif (isset($this->customMessages[$field])) {
            $message = $this->customMessages[$field];
        }

        $this->errors[$field][] = $message;
    }

    private function getFieldLabel(string $field): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace(['_', '-'], ' ', $field));
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    public function allErrors(): array
    {
        $all = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $all[] = $message;
            }
        }
        return $all;
    }
}

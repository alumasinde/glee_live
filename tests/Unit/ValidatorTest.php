<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Validator;

/**
 * Validator Unit Tests
 *
 * Tests all validation rules independently
 */
class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }

    public function testRequiredRule()
    {
        $data = ['name' => '', 'email' => 'test@example.com'];
        $rules = [
            'name' => 'required',
            'email' => 'required|email',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->validator->errors());
    }

    public function testEmailRule()
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => 'email'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->errors());

        // Valid email
        $data = ['email' => 'test@example.com'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testMinRule()
    {
        $data = ['password' => 'short'];
        $rules = ['password' => 'min:8'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid length
        $data = ['password' => 'longenough'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testMaxRule()
    {
        $data = ['username' => 'verylongusername'];
        $rules = ['username' => 'max:10'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid length
        $data = ['username' => 'short'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testBetweenRule()
    {
        $data = ['name' => 'ab'];
        $rules = ['name' => 'between:3,20'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid length
        $data = ['name' => 'valid'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testConfirmedRule()
    {
        $data = [
            'password' => 'password123',
            'password_confirmation' => 'different',
        ];
        $rules = ['password' => 'confirmed'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Matching confirmation
        $data = [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testRegexRule()
    {
        $data = ['phone' => 'abc'];
        $rules = ['phone' => 'regex:/^[0-9]+$/'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid pattern
        $data = ['phone' => '123456'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testUrlRule()
    {
        $data = ['website' => 'not-a-url'];
        $rules = ['website' => 'url'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid URL
        $data = ['website' => 'https://example.com'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testNumericRule()
    {
        $data = ['age' => 'not-a-number'];
        $rules = ['age' => 'numeric'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid numeric
        $data = ['age' => '25'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testIntegerRule()
    {
        $data = ['count' => '12.5'];
        $rules = ['count' => 'integer'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid integer
        $data = ['count' => '12'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testAlphaRule()
    {
        $data = ['name' => 'John123'];
        $rules = ['name' => 'alpha'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid alpha
        $data = ['name' => 'John'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testAlphaNumRule()
    {
        $data = ['username' => 'user_name'];
        $rules = ['username' => 'alpha_num'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid alphanumeric
        $data = ['username' => 'user123'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testInRule()
    {
        $data = ['status' => 'invalid'];
        $rules = ['status' => 'in:active,inactive,pending'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid value
        $data = ['status' => 'active'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testSameRule()
    {
        $data = ['password' => 'pass', 'confirm' => 'different'];
        $rules = ['password' => 'same:confirm'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Matching fields
        $data = ['password' => 'pass', 'confirm' => 'pass'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testDateRule()
    {
        $data = ['birthday' => 'not-a-date'];
        $rules = ['birthday' => 'date'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);

        // Valid date
        $data = ['birthday' => '2024-01-01'];
        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
    }

    public function testCustomMessages()
    {
        $data = ['email' => 'invalid'];
        $rules = ['email' => 'email'];
        $messages = ['email.email' => 'Please provide a valid email address'];

        $this->validator->validate($data, $rules, $messages);

        $errors = $this->validator->errors();
        $this->assertEquals('Please provide a valid email address', $errors['email'][0]);
    }

    public function testMultipleRules()
    {
        $data = ['password' => 'short'];
        $rules = ['password' => 'required|min:8|max:20'];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        $this->assertArrayHasKey('password', $this->validator->errors());
    }

    public function testHasErrors()
    {
        $data = ['field' => ''];
        $rules = ['field' => 'required'];

        $this->validator->validate($data, $rules);

        $this->assertTrue($this->validator->hasErrors());
    }

    public function testFirstError()
    {
        $data = ['email' => 'invalid'];
        $rules = ['email' => 'email'];

        $this->validator->validate($data, $rules);

        $firstError = $this->validator->firstError('email');
        $this->assertIsString($firstError);
        $this->assertNotEmpty($firstError);
    }

    public function testAllErrors()
    {
        $data = [
            'name' => '',
            'email' => 'invalid',
        ];
        $rules = [
            'name' => 'required',
            'email' => 'required|email',
        ];

        $this->validator->validate($data, $rules);

        $allErrors = $this->validator->allErrors();
        $this->assertCount(2, $allErrors);
    }
}

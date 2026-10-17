<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterParamCastTest extends TestCase
{
    /**
     * @return array{0: mixed, 1: string} captured [value, type]
     */
    private function dispatchUri(string $uri): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_SERVER['SCRIPT_NAME']    = '/index.php';

        $controller = new ParamCaptureController();
        $router     = new Router();
        $router->get('/gate/{id}', [ParamCaptureController::class, 'show']);

        // Router resolves controllers through the container; prime it with our
        // instance so the captured arguments are observable.
        \App\Core\App::container()->bind(
            ParamCaptureController::class,
            fn () => $controller
        );

        $router->dispatch(new Request());

        return $controller->captured;
    }

    public function testNumericSegmentIsCastToInt(): void
    {
        [$value, $type] = $this->dispatchUri('/gate/123');

        $this->assertSame('integer', $type);
        $this->assertSame(123, $value);
    }

    public function testLongAllDigitTokenStaysString(): void
    {
        $token = str_repeat('7', 64);
        [$value, $type] = $this->dispatchUri('/gate/' . $token);

        $this->assertSame('string', $type);
        $this->assertSame($token, $value);
    }

    public function testHexTokenStaysString(): void
    {
        [$value, $type] = $this->dispatchUri('/gate/0123456789abcdef0123456789abcdef');

        $this->assertSame('string', $type);
        $this->assertSame('0123456789abcdef0123456789abcdef', $value);
    }
}

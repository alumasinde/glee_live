<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;

class ParamCaptureController
{
    public array $captured = [];

    public function show(Request $request, mixed $id): string
    {
        $this->captured = [$id, gettype($id)];

        return 'ok';
    }
}

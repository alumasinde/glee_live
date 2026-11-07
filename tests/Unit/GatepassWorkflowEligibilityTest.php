<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Gatepass\Services\GatepassWorkflow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GatepassWorkflowEligibilityTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function pass(array $overrides = []): array
    {
        return array_merge([
            'status_code'   => 'APPROVED',
            'is_returnable' => 0,
            'actual_in'     => null,
            'actual_out'    => null,
        ], $overrides);
    }

    public function testNonReturnablePassMayCheckOutFromApproved(): void
    {
        $eligibility = GatepassWorkflow::eligibility($this->pass());

        $this->assertTrue($eligibility['checkin_eligible']);
        $this->assertTrue($eligibility['checkout_eligible']);
    }

    public function testReturnablePassCannotCheckOutBeforeCheckIn(): void
    {
        $eligibility = GatepassWorkflow::eligibility($this->pass(['is_returnable' => 1]));

        $this->assertTrue($eligibility['checkin_eligible']);
        $this->assertFalse(
            $eligibility['checkout_eligible'],
            'A returnable item must come in before it can go out.'
        );
    }

    public function testReturnablePassMayCheckOutAfterCheckIn(): void
    {
        $eligibility = GatepassWorkflow::eligibility($this->pass([
            'status_code'   => 'CHECKED_IN',
            'is_returnable' => 1,
            'actual_in'     => '2026-11-01 08:00:00',
        ]));

        $this->assertFalse($eligibility['checkin_eligible']);
        $this->assertTrue($eligibility['checkout_eligible']);
    }

    public function testCheckedOutPassIsNotEligibleForEitherGateAction(): void
    {
        $eligibility = GatepassWorkflow::eligibility($this->pass([
            'status_code'   => 'CHECKED_OUT',
            'is_returnable' => 1,
            'actual_in'     => '2026-11-01 08:00:00',
            'actual_out'    => '2026-11-01 12:00:00',
        ]));

        $this->assertFalse($eligibility['checkin_eligible']);
        $this->assertFalse($eligibility['checkout_eligible']);
    }

    public function testMissingStatusIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        GatepassWorkflow::eligibility(['is_returnable' => 0]);
    }
}

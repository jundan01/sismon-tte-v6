<?php

namespace Tests\Unit;

use App\Services\Security\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService();
    }

    public function test_can_generate_secret_and_recovery_codes(): void
    {
        $secret = $this->totpService->generateSecret();
        $this->assertNotEmpty($secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));

        $codes = $this->totpService->generateRecoveryCodes(8);
        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $code);
        }
    }

    public function test_verify_valid_recovery_code_and_consumes_it(): void
    {
        $storedCodes = ['a1b2-c3d4', 'e5f6-g7h8', '1122-3344'];
        
        $result = $this->totpService->verifyRecoveryCode($storedCodes, 'a1b2-c3d4');
        $this->assertNotNull($result);
        $this->assertCount(2, $result);
        $this->assertNotContains('a1b2-c3d4', $result);
        $this->assertContains('e5f6-g7h8', $result);
        $this->assertContains('1122-3344', $result);
    }

    public function test_verify_recovery_code_case_insensitive(): void
    {
        $storedCodes = ['a1b2-c3d4', 'e5f6-7788'];
        
        $result = $this->totpService->verifyRecoveryCode($storedCodes, 'A1B2-C3D4');
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertNotContains('a1b2-c3d4', $result);
    }

    public function test_verify_invalid_recovery_code_returns_null(): void
    {
        $storedCodes = ['a1b2-c3d4', 'e5f6-7788'];
        
        $result = $this->totpService->verifyRecoveryCode($storedCodes, '9999-0000');
        $this->assertNull($result);
    }

    public function test_verify_recovery_code_from_json_string(): void
    {
        $storedCodesJson = json_encode(['code-1111', 'code-2222']);
        
        $result = $this->totpService->verifyRecoveryCode($storedCodesJson, 'code-1111');
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals(['code-2222'], $result);
    }
}

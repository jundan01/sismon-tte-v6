<?php

namespace Tests\Unit;

use App\Domain\Master\Models\SystemParameter;
use App\Services\SystemParameterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemParameterServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_get_existing_parameter_with_proper_type_casting(): void
    {
        SystemParameter::updateOrCreate(
            ['param_key' => 'test_int_param'],
            [
                'param_value' => '14',
                'param_group' => 'TESTING',
                'data_type'   => 'INTEGER',
                'label'       => 'Test Integer Parameter',
            ]
        );

        SystemParameterService::clearCache();

        $value = SystemParameterService::get('test_int_param');
        $this->assertIsInt($value);
        $this->assertSame(14, $value);
    }

    public function test_get_non_existing_parameter_returns_default(): void
    {
        $value = SystemParameterService::get('non_existing_key_xyz', 'default_val');
        $this->assertSame('default_val', $value);
    }

    public function test_can_set_and_update_parameter(): void
    {
        SystemParameter::updateOrCreate(
            ['param_key' => 'test_bool_param'],
            [
                'param_value' => 'false',
                'param_group' => 'TESTING',
                'data_type'   => 'BOOLEAN',
                'label'       => 'Test Boolean Parameter',
            ]
        );

        SystemParameterService::clearCache();
        $this->assertFalse(SystemParameterService::get('test_bool_param'));

        $success = SystemParameterService::set('test_bool_param', true);
        $this->assertTrue($success);
        $this->assertTrue(SystemParameterService::get('test_bool_param'));
    }
}

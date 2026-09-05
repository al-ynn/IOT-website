<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organization;
use App\Services\TelemetrySchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParameterSchemaTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private function device(): Device
    {
        $organization = Organization::create(['name' => 'Schema Org', 'slug' => 'schema-org']);
        return $organization->devices()->create(['name' => 'Typed Device', 'external_id' => 'typed-device', 'type' => 'sensor', 'protocol' => 'mqtt']);
    }

    public function test_typed_values_are_validated_and_preserved(): void
    {
        $device = $this->device();
        $device->parameters()->create(['name' => 'Mode', 'key' => 'mode', 'data_type' => 'enum', 'configuration' => ['options' => ['auto', 'manual']], 'unit' => null]);
        $device->parameters()->create(['name' => 'Enabled', 'key' => 'enabled', 'data_type' => 'boolean', 'configuration' => [], 'unit' => null]);
        $device->parameters()->create(['name' => 'Temperature', 'key' => 'temperature', 'data_type' => 'number', 'configuration' => ['min' => 0, 'max' => 100], 'unit' => 'C']);

        $schema = app(TelemetrySchemaService::class);
        $this->assertSame('auto', $schema->record($device, 'mode', 'auto')->typed_value);
        $this->assertTrue($schema->record($device, 'enabled', true)->typed_value);
        $this->assertSame(23.5, $schema->record($device, 'temperature', 23.5)->typed_value);

        foreach ([['mode', 'invalid'], ['enabled', 1], ['temperature', 101]] as [$key, $value]) {
            try {
                $schema->record($device, $key, $value);
                $this->fail('Invalid telemetry was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_template_driven_device_rejects_unknown_parameter_keys(): void
    {
        $device = $this->device();
        $device->update(['device_template_id' => $device->organization->deviceTemplates()->create(['name' => 'Template', 'device_type' => 'sensor', 'protocol' => 'mqtt'])->id]);

        $this->expectException(ValidationException::class);
        app(TelemetrySchemaService::class)->record($device->fresh(), 'unknown_key', 12);
    }
}

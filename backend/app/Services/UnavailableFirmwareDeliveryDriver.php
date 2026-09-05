<?php
namespace App\Services;
use App\Contracts\FirmwareDeliveryDriver;use App\Models\Device;use App\Models\FirmwareArtifact;use RuntimeException;
class UnavailableFirmwareDeliveryDriver implements FirmwareDeliveryDriver { public function dispatch(Device $device,FirmwareArtifact $artifact):never { throw new RuntimeException('Firmware delivery is not configured.'); } }

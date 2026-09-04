<?php
namespace App\Contracts;
use App\Models\Device;use App\Models\FirmwareArtifact;
interface FirmwareDeliveryDriver { public function dispatch(Device $device,FirmwareArtifact $artifact):never; }

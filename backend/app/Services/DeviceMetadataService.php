<?php
namespace App\Services;
use App\Models\{Device,DeviceMetadataValue,DeviceTemplateMetadataDefinition};
use Illuminate\Validation\ValidationException;
final class DeviceMetadataService {
    public function definitions(Device $device) { return DeviceTemplateMetadataDefinition::where('device_template_id',$device->device_template_id)->orderBy('sort_order')->orderBy('id')->get(); }
    public function values(Device $device) { return DeviceMetadataValue::with('definition')->where('device_id',$device->id)->get()->keyBy('metadata_definition_id'); }
    public function set(Device $device, array $input): DeviceMetadataValue {
        $id=$input['definitionId']??null; $key=$input['key']??null;
        $q=DeviceTemplateMetadataDefinition::where('device_template_id',$device->device_template_id);
        $def=$id!==null?$q->whereKey($id)->first():$q->where('key',strtolower(trim((string)$key)))->first();
        if(!$def) throw ValidationException::withMessages(['definitionId'=>['Metadata definition is not available for this Device.']]);
        $value=$this->validateValue($def,$input['value']??null);
        return DeviceMetadataValue::updateOrCreate(['device_id'=>$device->id,'metadata_definition_id'=>$def->id],['value'=>$value])->load('definition');
    }
    private function validateValue(DeviceTemplateMetadataDefinition $d,mixed $v): mixed {
        $c=$d->configuration??[];
        if($v===null && $d->required) throw ValidationException::withMessages(['value'=>['This metadata value is required.']]);
        if($v===null) return null;
        switch($d->data_type){
            case 'string': if(!is_string($v)||mb_strlen($v)>($c['maxLength']??500)) throw ValidationException::withMessages(['value'=>['A valid bounded string is required.']]); return $v;
            case 'integer': if(filter_var($v,FILTER_VALIDATE_INT)===false || (string)(int)$v!==(string)$v && !is_int($v)) throw ValidationException::withMessages(['value'=>['A valid integer is required.']]); $n=(int)$v; break;
            case 'number': if(!is_numeric($v)||!is_finite((float)$v)) throw ValidationException::withMessages(['value'=>['A finite number is required.']]); $n=(float)$v; break;
            case 'boolean': if(!is_bool($v)) throw ValidationException::withMessages(['value'=>['A boolean is required.']]); return $v;
            case 'date': if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)||!strtotime($v)) throw ValidationException::withMessages(['value'=>['A valid date is required.']]); return $v;
            case 'datetime': if(!is_string($v)||!strtotime($v)) throw ValidationException::withMessages(['value'=>['A valid datetime is required.']]); return $v;
            case 'enum': $opts=$c['options']??$c['allowedValues']??[]; if(!is_string($v)||!in_array($v,$opts,true)) throw ValidationException::withMessages(['value'=>['Select a valid option.']]); return $v;
            default: throw ValidationException::withMessages(['value'=>['Unsupported metadata type.']]);
        }
        if(isset($c['min'])&&$n<$c['min']||isset($c['max'])&&$n>$c['max']) throw ValidationException::withMessages(['value'=>['Value is outside the configured range.']]); return $d->data_type==='integer'?(int)$n:$n;
    }
}

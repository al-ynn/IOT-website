<?php
namespace App\Services;
use App\Models\{Report,ResourceRevision,User};use Illuminate\Validation\ValidationException;
final class ReportPublicationValidator{
 public function __construct(private ReportConfigurationService $configuration){}
 public function validate(Report $report,ResourceRevision $revision,User $actor):array{
  if($revision->resource_type!=='report'||(int)$revision->resource_id!==(int)$report->id)throw ValidationException::withMessages(['revision_id'=>['Revision does not belong to this Report.']]);
  $snapshot=$revision->snapshot;$type=data_get($snapshot,'metadata.reportType');$config=$snapshot['configuration']??null;
  if(!is_string($type)||!is_array($config))throw ValidationException::withMessages(['revision'=>['Report revision snapshot is incomplete.']]);
  $clean=$this->configuration->validate($actor,$type,$config);$this->secrets($snapshot);return ['report_type'=>$type,'configuration'=>$clean];
 }
 private function secrets(array $values):void{foreach($values as $key=>$value){if(preg_match('/^(authorization|password|secret|credential|access_token|refresh_token|api_key|private_key|cookie)$/i',(string)$key))throw ValidationException::withMessages(['revision'=>['Publication cannot contain secrets.']]);if(is_array($value))$this->secrets($value);}}
}

<?php

namespace App\Services;

final class RevisionSnapshotPresenter
{
    private const SECRET_KEYS=['authorization','authorization_header','password','secret','signing_secret','client_secret','access_token','refresh_token','token','api_key','private_key','app_key','db_password','credentials','accesstoken','refreshtoken','apikey','privatekey','signingsecret','clientsecret','authorizationheader','appkey','dbpassword'];
    private const SOURCE_KEYS=['deviceid','device_id','device_ids','telemetrykey','telemetry_key','metric_keys','parameter_id','parameter_ids','source_id','source_ids','deviceids','metrickeys','parameterid','parameterids','sourceid','sourceids'];

    public function present(string $type,array $snapshot):array
    {
        $safe=$this->walk($snapshot);
        if($type==='webhook'&&isset($safe['configuration'])&&is_array($safe['configuration'])){
            $configured=array_key_exists('url',$safe['configuration']);
            unset($safe['configuration']['url']);
            $safe['configuration']['urlConfigured']=$configured;
        }
        if($type==='report'&&isset($snapshot['configuration'])&&is_array($snapshot['configuration'])){
            $safe['configuration']['deviceCount']=count((array)($snapshot['configuration']['device_ids']??[]));
            $safe['configuration']['metricCount']=count((array)($snapshot['configuration']['metric_keys']??[]));
        }
        return $safe;
    }

    private function walk(array $value):array
    {
        $result=[];
        foreach($value as $key=>$item){$normalized=strtolower(preg_replace('/[^a-z0-9]+/i','_', (string)$key));if(in_array($normalized,self::SECRET_KEYS,true)||in_array($normalized,self::SOURCE_KEYS,true))continue;$result[$key]=is_array($item)?$this->walk($item):$item;}
        return $result;
    }
}
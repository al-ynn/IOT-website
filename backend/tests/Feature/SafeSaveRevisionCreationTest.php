<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Services\ResourceRevisionService;
use App\Services\SafeResourceSaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SafeSaveRevisionCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_rejects_missing_and_foreign_base_before_mutation(): void
    {
        $org=Organization::create(['name'=>'Safe Save','slug'=>'safe-save']);
        $actor=User::factory()->create(['organization_id'=>$org->id,'role'=>'owner','status'=>'active']);
        $report=$org->reports()->create(['name'=>'Original','report_type'=>'device_summary','configuration'=>['device_ids'=>[]],'created_by'=>$actor->id,'updated_by'=>$actor->id]);
        ResourceRevisionService::class;
        $current=app(ResourceRevisionService::class)->recordReport($report,$actor,'Baseline');
        $other=$org->reports()->create(['name'=>'Other','report_type'=>'device_summary','configuration'=>['device_ids'=>[]],'created_by'=>$actor->id,'updated_by'=>$actor->id]);
        $foreign=app(ResourceRevisionService::class)->recordReport($other,$actor,'Baseline');
        $mutations=0;
        $run=function($base)use($actor,$report,&$mutations){return app(SafeResourceSaveService::class)->execute($actor,'report',Report::class,$report->id,$base,fn()=>true,fn()=>true,function(Report $locked)use(&$mutations){$mutations++;$locked->update(['name'=>'Changed']);},fn()=>true,true);};

        try{$run(null);$this->fail('Missing base was accepted.');}catch(ValidationException $e){$this->assertArrayHasKey('base_revision_id',$e->errors());}
        try{$run($foreign->id);$this->fail('Foreign base was accepted.');}catch(ValidationException $e){$this->assertArrayHasKey('base_revision_id',$e->errors());}

        $this->assertSame(0,$mutations);
        $this->assertSame('Original',$report->refresh()->name);
        $this->assertSame(1,ResourceRevision::where(['resource_type'=>'report','resource_id'=>$report->id])->count());
        $this->assertSame($current->id,ResourceRevision::where(['resource_type'=>'report','resource_id'=>$report->id])->first()->id);
    }

    public function test_exception_after_mutation_rolls_back_resource_and_revision_scope(): void
    {
        $org=Organization::create(['name'=>'Atomic','slug'=>'atomic-save']);
        $actor=User::factory()->create(['organization_id'=>$org->id,'role'=>'owner','status'=>'active']);
        $device=$org->devices()->create(['name'=>'Original','external_id'=>'safe-atomic-1','status'=>'online','type'=>'sensor','protocol'=>'mqtt']);

        try{app(SafeResourceSaveService::class)->execute($actor,'device',Device::class,$device->id,null,fn()=>true,fn()=>true,function(Device $locked){$locked->update(['name'=>'Partial']);},function(){throw new \RuntimeException('revision failed');});$this->fail('Expected failure.');}catch(\RuntimeException $e){$this->assertSame('revision failed',$e->getMessage());}

        $this->assertSame('Original',$device->refresh()->name);
        $this->assertDatabaseCount('resource_revisions',0);
    }
}

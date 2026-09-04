<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Dashboard;
use App\Models\DeviceTemplate;
use App\Models\FirmwareArtifact;
use App\Models\Automation;
use App\Models\Report;
use App\Models\ResourceRevision;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Location;
use App\Revisions\DeviceRevisionSnapshotBuilder;
use App\Revisions\DashboardRevisionSnapshotBuilder;
use App\Revisions\DeviceTemplateRevisionSnapshotBuilder;
use App\Revisions\FirmwareRevisionSnapshotBuilder;
use App\Revisions\AutomationRevisionSnapshotBuilder;
use App\Revisions\ReportRevisionSnapshotBuilder;
use App\Revisions\WebhookRevisionSnapshotBuilder;
use App\Revisions\LocationRevisionSnapshotBuilder;
use Illuminate\Support\Facades\DB;

final class ResourceRevisionService
{
    public function __construct(private DeviceRevisionSnapshotBuilder $devices, private DeviceTemplateRevisionSnapshotBuilder $templates,private FirmwareRevisionSnapshotBuilder $firmware, private AutomationRevisionSnapshotBuilder $automations,private ReportRevisionSnapshotBuilder $reports, private WebhookRevisionSnapshotBuilder $webhooks, private LocationRevisionSnapshotBuilder $locations, private DashboardRevisionSnapshotBuilder $dashboards, private ResourceRevisionSectionResolver $sections) {}

    public function recordDashboard(Dashboard $dashboard, ?User $actor, string $summary): ResourceRevision
    {
        return DB::transaction(function () use ($dashboard, $actor, $summary) {
            Dashboard::whereKey($dashboard->id)->lockForUpdate()->firstOrFail();
            $snapshot=$this->dashboards->build($dashboard->refresh());
            $checksum=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            $latest=ResourceRevision::where(['resource_type'=>'dashboard','resource_id'=>$dashboard->id])->latest('revision_number')->lockForUpdate()->first();
            if($latest&&hash_equals($latest->checksum,$checksum))return $latest;
            return ResourceRevision::create(['resource_type'=>'dashboard','resource_id'=>$dashboard->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>DashboardRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('dashboard',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);
        });
    }
    public function recordLocation(Location $location, ?User $actor, string $summary): ResourceRevision
    {
        return DB::transaction(function () use ($location, $actor, $summary) {
            Location::whereKey($location->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->locations->build($location->refresh());
            $checksum = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $latest = ResourceRevision::where(['resource_type'=>'location','resource_id'=>$location->id])->latest('revision_number')->lockForUpdate()->first();
            if ($latest && hash_equals($latest->checksum, $checksum)) return $latest;
            return ResourceRevision::create(['resource_type'=>'location','resource_id'=>$location->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>LocationRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('location',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);
        });
    }
    public function recordWebhook(Webhook $webhook,?User $actor,string $summary):ResourceRevision
    {
        return DB::transaction(function()use($webhook,$actor,$summary){Webhook::whereKey($webhook->id)->lockForUpdate()->firstOrFail();$snapshot=$this->webhooks->build($webhook->refresh());$checksum=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$latest=ResourceRevision::where(['resource_type'=>'webhook','resource_id'=>$webhook->id])->latest('revision_number')->lockForUpdate()->first();if($latest&&hash_equals($latest->checksum,$checksum))return $latest;return ResourceRevision::create(['resource_type'=>'webhook','resource_id'=>$webhook->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>WebhookRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('webhook',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);});
    }
    public function recordReport(Report $report,?User $actor,string $summary):ResourceRevision
    {
        return DB::transaction(function()use($report,$actor,$summary){Report::whereKey($report->id)->lockForUpdate()->firstOrFail();$snapshot=$this->reports->build($report->refresh());$checksum=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$latest=ResourceRevision::where(['resource_type'=>'report','resource_id'=>$report->id])->latest('revision_number')->lockForUpdate()->first();if($latest&&hash_equals($latest->checksum,$checksum))return $latest;return ResourceRevision::create(['resource_type'=>'report','resource_id'=>$report->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>ReportRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('report',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);});
    }

    public function recordAutomation(Automation $automation, ?User $actor, string $summary): ResourceRevision
    {
        return DB::transaction(function () use ($automation, $actor, $summary) {
            Automation::whereKey($automation->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->automations->build($automation->refresh());
            $checksum = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $latest = ResourceRevision::where(['resource_type' => 'automation', 'resource_id' => $automation->id])->latest('revision_number')->lockForUpdate()->first();
            if ($latest && hash_equals($latest->checksum, $checksum)) return $latest;
            return ResourceRevision::create(['resource_type'=>'automation','resource_id'=>$automation->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>AutomationRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('automation',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);
        });
    }

    public function recordFirmware(FirmwareArtifact $artifact,?User $actor,string $summary):ResourceRevision
    {
        return DB::transaction(function()use($artifact,$actor,$summary){FirmwareArtifact::whereKey($artifact->id)->lockForUpdate()->firstOrFail();$snapshot=$this->firmware->build($artifact->refresh());$checksum=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$latest=ResourceRevision::where(['resource_type'=>'firmware','resource_id'=>$artifact->id])->latest('revision_number')->lockForUpdate()->first();if($latest&&hash_equals($latest->checksum,$checksum))return $latest;return ResourceRevision::create(['resource_type'=>'firmware','resource_id'=>$artifact->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>FirmwareRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('firmware',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);});
    }

    public function recordTemplate(DeviceTemplate $template, ?User $actor, string $summary): ResourceRevision
    {
        return DB::transaction(function () use ($template,$actor,$summary) {
            DeviceTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();$snapshot=$this->templates->build($template->refresh());$encoded=json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$checksum=hash('sha256',$encoded);$latest=ResourceRevision::where('resource_type','device_template')->where('resource_id',$template->id)->orderByDesc('revision_number')->lockForUpdate()->first();if($latest&&hash_equals($latest->checksum,$checksum))return $latest;$revision=ResourceRevision::create(['resource_type'=>'device_template','resource_id'=>$template->id,'revision_number'=>($latest?->revision_number??0)+1,'parent_revision_id'=>$latest?->id,'created_by'=>$actor?->id,'change_summary'=>$summary,'snapshot_schema_version'=>DeviceTemplateRevisionSnapshotBuilder::SCHEMA_VERSION,'snapshot'=>$snapshot,'changed_sections'=>$this->sections->resolve('device_template',$latest?->snapshot,$snapshot),'checksum'=>$checksum,'created_at'=>now()]);return $revision;
        });
    }

    public function recordDevice(Device $device, ?User $actor, string $summary): ResourceRevision
    {
        return DB::transaction(function () use ($device, $actor, $summary) {
            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->devices->build($device->refresh());
            $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $checksum = hash('sha256', $encoded);
            $latest = ResourceRevision::query()->where('resource_type', 'device')->where('resource_id', $device->id)->orderByDesc('revision_number')->lockForUpdate()->first();
            if ($latest && hash_equals($latest->checksum, $checksum)) return $latest;
            $changed = $this->sections->resolve('device', $latest?->snapshot, $snapshot);
            $revision = ResourceRevision::query()->create([
                'resource_type' => 'device', 'resource_id' => $device->id,
                'revision_number' => ($latest?->revision_number ?? 0) + 1,
                'parent_revision_id' => $latest?->id, 'created_by' => $actor?->id,
                'change_summary' => $summary, 'snapshot_schema_version' => DeviceRevisionSnapshotBuilder::SCHEMA_VERSION,
                'snapshot' => $snapshot, 'changed_sections' => $changed, 'checksum' => $checksum, 'created_at' => now(),
            ]);
            app(ResourceRevisionStateService::class)->revisionCreated($device, $revision, $actor);
            return $revision;
        });
    }

}
<?php
namespace App\Console\Commands;use App\Services\RevisionReminderService;use Illuminate\Console\Command;
final class ProcessRevisionReminders extends Command{protected $signature='revisions:process-reminders {--limit=100}';protected $description='Process due collaborative revision reminders';public function handle(RevisionReminderService $service):int{$count=$service->processDue(max(1,(int)$this->option('limit')));$this->info("Processed $count revision reminders.");return self::SUCCESS;}}

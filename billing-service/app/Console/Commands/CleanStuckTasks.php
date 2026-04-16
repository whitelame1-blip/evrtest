<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class CleanStuckTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-stuck-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    // 1. Define the timeout (e.g., tasks older than 10 minutes)
    $timeoutLimit = now()->subMinutes(10);

    // 2. Get the tasks that are stuck so we can refund them
    $stuckTasks = DB::table('user_tasks')
        ->where('status', 'pending')
        ->where('created_at', '<', $timeoutLimit)
        ->get();

    if ($stuckTasks->isEmpty()) {
        $this->info("Janitor: No stuck tasks found.");
        return;
    }

    foreach ($stuckTasks as $task) {
        DB::transaction(function () use ($task) {
            // 3. Mark the task as failed
            DB::table('user_tasks')->where('id', $task->id)->update([
                'status' => 'failed',
                'updated_at' => now()
            ]);

            // 4. Auto-Refund Credits
            // We need to know how much to refund. 
            // You can use a match case based on task_type or store cost in the table.
            $refundAmount = match($task->task_type) {
                'listening_gen', 'reading_gen' => 3, // Adjust these to your actual costs
                'voice_analysis' => 2,
                default => 1,
            };

            DB::table('credits')
                ->where('user_id', $task->user_id)
                ->increment('balance', $refundAmount);
        });

        $this->info("Janitor: Refunded {$task->task_id} and marked as failed.");
    }

    $this->info("Janitor: Cleanup complete.");
}
}

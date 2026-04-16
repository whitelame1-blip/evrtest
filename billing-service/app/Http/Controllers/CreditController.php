<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class CreditController extends Controller
{
    public function refund(Request $request) 
    {
        Log::info("Refund & Status Update hit!", [
            'user_id' => $request->input('user_id'),
            'task_id' => $request->input('task_id'), // We need to receive the task_id from Python
            'amount'  => $request->input('amount'),
        ]);

        $secret = $request->header('X-Internal-Secret');
        if (!$secret || $secret !== config('services.ai.secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userId = $request->input('user_id');
        $taskId = $request->input('task_id'); 
        $amount = $request->input('amount');

        if (!$userId || !$amount) {
            return response()->json(['error' => 'Invalid data'], 400);
        }

        return DB::transaction(function () use ($userId, $taskId, $amount) {
            // 1. REFUND THE CREDITS (Table: credits, Column: balance)
            $creditRecord = DB::table('credits')->where('user_id', $userId)->exists();
            if ($creditRecord) {
                DB::table('credits')
                    ->where('user_id', $userId)
                    ->increment('balance', $amount);
                Log::info("Credits refunded for user $userId");
            }

            // 2. UPDATE TASK STATUS (Table: user_tasks)
            // We search by the taskId sent from Python
            if ($taskId) {
                DB::table('user_tasks')
                    ->where('task_id', $taskId)
                    ->update(['status' => 'failed']);
                Log::info("Task $taskId status set to failed");
            }

            return response()->json(['status' => 'success']);
        });
    }
}
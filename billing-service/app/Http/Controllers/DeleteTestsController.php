<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteTestsController extends Controller
{
    /**
     * Delete a saved test session.
     */
    public function destroy(Request $request)
    {
        // 1. Get the authenticated user
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false, 
                'message' => 'Session expired.'
            ], 401);
        }

        // 2. Validate input
        $testId = $request->input('test_id');
        $category = $request->input('category');

        if (!$testId || !$category) {
            return response()->json([
                'success' => false, 
                'message' => 'Missing ID or Category'
            ], 400);
        }

        // 3. Determine the target table
        $targetTable = "";
        switch ($category) {
            case 'listening': 
                $targetTable = "practice_sessions"; 
                break;
            case 'single': 
            case 'reading': // Added reading for consistency
                $targetTable = "reading_tests"; 
                break;
            default:
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid category'
                ], 400);
        }

        try {
            // 4. Execute deletion with ownership check
            $deleted = DB::table($targetTable)
                ->where('id', $testId)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted) {
                return response()->json(['success' => true]);
            } else {
                return response()->json([
                    'success' => false, 
                    'message' => 'Record not found or access denied.'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Server error occurred during deletion.'
            ], 500);
        }
    }
}
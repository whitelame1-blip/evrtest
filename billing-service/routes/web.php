<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // <--- ADD THIS  // <--- ADD THIS
use Illuminate\Support\Facades\Log; // For your logging
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\AIGeneratorController;
use App\Http\Controllers\VoiceController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\TakeTestController;
use App\Http\Controllers\TestResultController;
use App\Http\Controllers\DownloadTestsController;
use App\Http\Controllers\DeleteTestsController;
use App\Http\Controllers\CompletedTestsController;
use App\Http\Controllers\AiReviewController;
use App\Http\Controllers\UpdateProfileController;

// --- 1. Public / Guest Routes ---
Route::get('/', function () { return view('welcome'); });

// Google Auth
Route::get('auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

// --- 2. Authenticated Dashboard & Views ---
Route::middleware(['auth'])->group(function () {
    
    // The main entry point (using the merged HomeController we built)
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    
    // Test Data fetch
    Route::get('/saved-tests-data', [AIGeneratorController::class, 'getSavedTests']);

    // --- 3. API / Logic Routes ---
    Route::prefix('api')->group(function () {
        
        // Notification System
        Route::get('/notifications/counts', [AIGeneratorController::class, 'getNotificationCounts']);
        Route::post('/notifications/dismiss', [AIGeneratorController::class, 'dismissNotifications']);

        // AI Generation Module
        Route::get('/voices', [AIGeneratorController::class, 'getVoices']);
        Route::post('/voices/preview', [AIGeneratorController::class, 'previewVoice']);
        Route::post('/generate/listening', [AIGeneratorController::class, 'generateListening']);
        Route::post('/generate/reading', [AIGeneratorController::class, 'generateReading']);
        Route::post('/ai-review', [AIGeneratorController::class, 'aiReview']);
        Route::get('/task-status/{taskId}', [AIGeneratorController::class, 'checkStatus']);
        Route::post('/tests/generate-ai-review', [AIGeneratorController::class, 'generateAiReview']);

        // Voice/Audio Analysis
        Route::post('/ai-review', [VoiceController::class, 'aiReview']);

        Route::post('/pronunciation-tips', [VoiceController::class, 'pronunciationTips']);
        Route::post('/analyze-audio', [VoiceController::class, 'analyzeAudio']);

        // Gaming/Arena Mode
        Route::post('/generate-game', [AIGeneratorController::class, 'generateGame'])
    ->middleware(['auth', 'throttle:5,1']);

        Route::get('/user/balance', function() {
    // Auth::id() is the most reliable way to get the ID
    $userId = Auth::id(); 

    if (!$userId) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $balance = DB::table('credits')->where('user_id', $userId)->value('balance');

    return response()->json([
        'balance' => $balance ?? 0 // Default to 0 if no row exists
    ]);
});
    });
    Route::get('/test/listening/{id}', [TakeTestController::class, 'showListeningTest'])->name('test.listening');
    
    Route::get('/test/reading/{id}', [TakeTestController::class, 'showReadingTest'])->name('test.reading');
    Route::post('/api/test/{id}/register-start', [TakeTestController::class, 'registerStart']);
    Route::post('/api/test/{id}/acknowledge-warning', [TakeTestController::class, 'acknowledgeInterruption']);

// Route to handle the submission
Route::post('/submit-test', [TestResultController::class, 'submitReading'])->name('test.submit');

// Route to show the results (GET)
Route::get('/test-results/{test_id}', [TestResultController::class, 'showResults'])->name('test.results');

// Handle AJAX language switch 
Route::post('/set-language', [TakeTestController::class, 'setLanguage'])->name('set_language');
// The submission route
Route::post('/test/submit', [TestResultController::class, 'submitListening'])->name('test.submitListening');

// The results display route (Post-Redirect-Get pattern to prevent resubmission)
Route::get('/test/results/{id}', [TestResultController::class, 'show'])->name('test.results.show');


// game routes

// Example API endpoints for your Javascript
// Note: You will need to build the controller functions to handle these actions next!
Route::post('/games/exchange-xp', [GameController::class, 'exchangeXp']);
Route::get('/games/user-stats', [GameController::class, 'getUserStats']);
Route::post('/games/update-avatar', [GameController::class, 'updateAvatar']);
Route::get('/single-play', [GameController::class, 'singlePlayDashboard'])->name('game.single_play');
    Route::post('/single-play/nickname', [GameController::class, 'storeNickname'])->name('game.store_nickname');
    
    // Active Game Session
    Route::get('/single-play/game', [GameController::class, 'playGame'])->name('game.single_play_game');
Route::post('/mission/{mission_id}/submit', [GameController::class, 'submitMission'])->name('mission.submit');
    
    // View the debrief (GET)
    Route::get('/mission/{mission_id}/debrief', [GameController::class, 'showDebrief'])->name('mission.debrief');
    Route::post('/credits/exchange', [GameController::class, 'exchange'])->middleware('auth');
    Route::post('/mission/{id}/abort', [GameController::class, 'abort'])->name('mission.abort');
//download tests
Route::post('/download/listening/{id}', [DownloadTestsController::class, 'downloadListening'])
         ->name('download.listening');
Route::post('/download/reading/{id}', [DownloadTestsController::class, 'downloadReading'])
         ->name('download.reading');


//delete tests
Route::post('/delete-test', [DeleteTestsController::class, 'destroy'])->name('test.delete');

//completed tests & performance history
Route::get('/tests/review/{id}', [CompletedTestsController::class, 'review'])->middleware('auth');

//ai-review for completed tests
Route::get('/ai-review-feedback', [AiReviewController::class, 'show'])->middleware('auth');

//profile update
Route::post('/profile/request-email-code', [UpdateProfileController::class, 'requestCode']);
Route::post('/profile/verify-email-code', [UpdateProfileController::class, 'verifyEmailCode']);
Route::post('/profile/update', [UpdateProfileController::class, 'updateProfile']);
});



Route::post('/internal/refund-credits', [CreditController::class, 'refund']);

Route::get('/login', function () {
    return view('auth.index'); // This points to resources/views/auth/login.blade.php
})->name('login');


// 2. This handles the JavaScript FETCH/FORM submission
Route::post('/login', [AuthController::class, 'login']);
// Laravel standard Auth routes
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::get('/verify', [AuthController::class, 'verify'])->name('verify');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
// Show the form
Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset');

// Handle the submission
Route::post('/reset-password', [AuthController::class, 'updatePassword'])->name('password.update');
// In routes/web.php
Route::get('/password-reset-success', function () {
    return view('auth.reset-success');
})->name('password.reset.success');
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success | Kolibri AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    {{-- Automatic redirect after 5 seconds --}}
    <meta http-equiv="refresh" content="5;url={{ route('login') }}">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md text-center">
        <div class="bg-white rounded-3xl shadow-2xl p-10 md:p-12">
            <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <h1 class="text-2xl font-bold text-slate-900 mb-2">Password Reset!</h1>
            <p class="text-slate-500 mb-8">Your password has been updated successfully.</p>
            
            <div class="text-sm text-slate-400">
                Redirecting to login in <span id="countdown" class="font-bold text-orange-500">5</span> seconds...
            </div>

            <a href="{{ route('login') }}" class="mt-6 inline-block text-orange-500 font-semibold hover:underline">
                Click here if you aren't redirected
            </a>
        </div>
    </div>

    <script>
        let timeLeft = 5;
        const timer = setInterval(() => {
            timeLeft--;
            document.getElementById('countdown').textContent = timeLeft;
            if (timeLeft <= 0) clearInterval(timer);
        }, 1000);
    </script>
</body>
</html>
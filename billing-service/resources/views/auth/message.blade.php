<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .message-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
            color: {{ $themeColor }}
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 24px; }
        p { color: #666; line-height: 1.6; margin-bottom: 30px; }
        .btn {
            display: inline-block;
            background-color: #333;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn:hover { background-color: #555; }
    </style>
</head>
<body>
    <div class="message-card">
        <div class="icon">
            <i class="fas {{ $iconClass }}"></i>
        </div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ route('login') }}" class="btn">Continue to Login</a>
    </div>
</body>
</html>
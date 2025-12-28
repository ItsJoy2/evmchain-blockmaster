<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVM-GETWAY - Admin Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .logo {
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #1a2a6c;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .logo span {
            color: #b21f1f;
        }

        .logo p {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .login-form {
            text-align: left;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .input-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .text-input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .text-input:focus {
            border-color: #1a2a6c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 42, 108, 0.2);
        }

        .input-error {
            color: #b21f1f;
            font-size: 14px;
            margin-top: 5px;
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input {
            margin-right: 8px;
        }

        .forgot-password {
            color: #1a2a6c;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .primary-button {
            background: linear-gradient(to right, #1a2a6c, #b21f1f);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(26, 42, 108, 0.3);
        }

        .primary-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 42, 108, 0.4);
        }

        .auth-session-status {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            color: #0c5460;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .logo h1 {
                font-size: 28px;
            }

            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
            }

            .forgot-password {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <img src="/logo.png" alt="logo" height="70">
        <p>Administrator Access Portal</p>
    </div>

    <!-- Laravel Session Status -->
    @if (session('status'))
        <div class="auth-session-status">
            {{ session('status') }}
        </div>
    @endif

    <!-- Validation Errors -->
    @if ($errors->any())
        <div class="auth-session-status" style="background-color:#f8d7da;color:#721c24;border-color:#f5c6cb;">
            <ul style="margin-left:20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="login-form">
        @csrf

        <!-- Email Address -->
        <div class="form-group">
            <label for="email" class="input-label">Email</label>
            <input id="email" class="text-input" type="email" name="email"
                   value="{{ old('email') }}" required autofocus
                   placeholder="Enter your email">
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password" class="input-label">Password</label>
            <div class="password-container">
                <input id="password" class="text-input" type="password"
                       name="password" required placeholder="Enter your password">
                <button type="button" class="toggle-password" id="togglePassword">Show</button>
            </div>
        </div>

        <!-- Remember Me -->
        <div class="remember-forgot">
            <div class="remember-me">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Remember me</span>
            </div>

            @if (Route::has('password.request'))
                <a class="forgot-password" href="{{ route('password.request') }}">
                    Forgot your password?
                </a>
            @endif
        </div>

        <button type="submit" class="primary-button">
            Log in
        </button>
    </form>

    <div class="footer">
        <p>© 2025 EVM-GETWAY. All rights reserved.</p>
        <p>Restricted access. Authorized personnel only.</p>
    </div>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            this.textContent = 'Hide';
        } else {
            passwordInput.type = 'password';
            this.textContent = 'Show';
        }
    });
</script>
</body>
</html>

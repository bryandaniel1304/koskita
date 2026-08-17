<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - KOSKITA</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="{{ asset('js/loading-bar.js') }}"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0F172A;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            max-width: 420px;
            width: 100%;
            background-color: #1E293B;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            color: #F8FAFC;
        }

        .login-logo-badge {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            overflow: hidden;
        }

        .login-logo-badge img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .form-control-custom {
            background-color: #0F172A;
            border: 1px solid #334155;
            color: #F8FAFC;
            border-radius: 10px;
            padding: 12px 16px;
        }

        .form-control-custom:focus {
            background-color: #0F172A;
            border-color: #7091F9;
            box-shadow: none;
            color: #F8FAFC;
        }

        .btn-primary-custom {
            background-color: #355DDB;
            border: none;
            font-weight: 700;
            padding: 14px;
            border-radius: 10px;
            color: #FFF;
            width: 100%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary-custom:hover {
            background-color: #2137A2;
        }

        .btn-primary-custom:disabled {
            opacity: 0.75;
        }

        .text-muted-custom {
            color: #94A3B8;
        }

        .spinner-border-sm-custom {
            width: 1rem;
            height: 1rem;
            border-width: 2px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo-badge">
                <img src="{{ asset('images/logo_icon.png') }}" alt="KOSKITA">
            </div>
            <h3 class="fw-bold mb-1">KOSKITA</h3>
            <p class="text-muted-custom small">Administrator Sign In</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger border-0 small py-2 px-3 mb-4" style="border-radius: 8px; background-color: rgba(244, 63, 94, 0.1); color: #F43F5E;">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.login.submit') }}" method="POST" id="admin-login-form">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label small text-muted-custom">Alamat Email</label>
                <input type="email" class="form-control form-control-custom" id="email" name="email" value="{{ old('email') }}" required placeholder="email@koskita.com">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label small text-muted-custom">Password</label>
                <input type="password" class="form-control form-control-custom" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-primary-custom" id="admin-login-submit">
                <span id="admin-login-submit-text">Masuk ke Dashboard</span>
            </button>
        </form>
    </div>

    <script>
        document.getElementById('admin-login-form').addEventListener('submit', function () {
            var btn = document.getElementById('admin-login-submit');
            var text = document.getElementById('admin-login-submit-text');
            btn.disabled = true;
            text.innerHTML = '<span class="spinner-border spinner-border-sm-custom" role="status" aria-hidden="true"></span> Memproses...';
        });
    </script>

</body>
</html>

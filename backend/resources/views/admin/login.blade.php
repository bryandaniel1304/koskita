<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - KOSKITA</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        .form-control-custom {
            background-color: #0F172A;
            border: 1px solid #334155;
            color: #F8FAFC;
            border-radius: 10px;
            padding: 12px 16px;
        }

        .form-control-custom:focus {
            background-color: #0F172A;
            border-color: #6366F1;
            box-shadow: none;
            color: #F8FAFC;
        }

        .btn-primary-custom {
            background-color: #6366F1;
            border: none;
            font-weight: 700;
            padding: 14px;
            border-radius: 10px;
            color: #FFF;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            background-color: #4F46E5;
        }

        .text-muted-custom {
            color: #94A3B8;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold mb-1">KOSKITA</h3>
            <p class="text-muted-custom small">Administrator Sign In</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger border-0 small py-2 px-3 mb-4" style="border-radius: 8px; background-color: rgba(244, 63, 94, 0.1); color: #F43F5E;">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.login.submit') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label small text-muted-custom">Alamat Email</label>
                <input type="email" class="form-control form-control-custom" id="email" name="email" value="{{ old('email') }}" required placeholder="email@koskita.com">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label small text-muted-custom">Password</label>
                <input type="password" class="form-control form-control-custom" id="password" name="password" required placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn btn-primary-custom">Masuk ke Dashboard</button>
        </form>
    </div>

</body>
</html>

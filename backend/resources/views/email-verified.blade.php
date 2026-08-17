<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Terverifikasi - KOSKITA</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
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
        .card-box {
            max-width: 420px;
            width: 100%;
            background-color: #1E293B;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            color: #F8FAFC;
            text-align: center;
        }
        .logo-badge {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            overflow: hidden;
        }
        .logo-badge img { width: 100%; height: 100%; object-fit: cover; }
        .check-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: rgba(16, 185, 129, 0.15);
            color: #10B981;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
        }
        .text-muted-custom { color: #94A3B8; }
    </style>
</head>
<body>
    <div class="card-box">
        <div class="logo-badge">
            <img src="{{ asset('images/logo_icon.png') }}" alt="KOSKITA">
        </div>
        <div class="check-icon">&#10003;</div>
        @if($alreadyVerified)
            <h4 class="fw-bold mb-2">Email Sudah Terverifikasi</h4>
            <p class="text-muted-custom mb-0">Akun kamu sudah terverifikasi sebelumnya. Silakan kembali ke app KOSKITA.</p>
        @else
            <h4 class="fw-bold mb-2">Email Berhasil Diverifikasi!</h4>
            <p class="text-muted-custom mb-0">Terima kasih, akun kamu sekarang sudah terverifikasi. Silakan kembali ke app KOSKITA untuk melanjutkan.</p>
        @endif
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOSKITA - API & Recommendation Engine</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366F1;
            --primary-dark: #4F46E5;
            --secondary: #F43F5E;
            --bg: #0F172A;
            --card-bg: #1E293B;
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --border: #334155;
            --success: #10B981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            width: 100%;
        }

        header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container {
            display: inline-flex;
            padding: 16px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 50%;
            border: 2px solid rgba(99, 102, 241, 0.2);
            margin-bottom: 16px;
            color: var(--primary);
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #FFF, #94A3B8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            font-weight: 300;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
        }

        .card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: 6px 12px;
            border-radius: 100px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--success);
        }

        .stat-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
        }

        .stat-label {
            color: var(--text-muted);
        }

        .stat-value {
            font-weight: 600;
        }

        .endpoints-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.875rem;
        }

        .endpoints-table th, .endpoints-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .endpoints-table th {
            color: var(--text-muted);
            font-weight: 600;
        }

        .method {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .method.get {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }

        .method.post {
            background-color: rgba(244, 63, 94, 0.1);
            color: var(--secondary);
        }

        footer {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 20px;
        }

        footer a {
            color: var(--primary);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <h1>KOSKITA API Services</h1>
            <p class="subtitle">Mesin Rekomendasi Kos Adaptif & Sistem Otentikasi</p>
        </header>

        <div class="grid">
            <!-- Engine Status Card -->
            <div class="card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                    Status Server
                </h2>
                <div style="margin-bottom: 20px;">
                    <div class="status-badge">
                        <span class="status-dot"></span>
                        Aktif & Terkoneksi
                    </div>
                </div>
                <ul class="stat-list">
                    <li class="stat-item">
                        <span class="stat-label">Database</span>
                        <span class="stat-value">SQLite (Lokal)</span>
                    </li>
                    <li class="stat-item">
                        <span class="stat-label">Algoritma</span>
                        <span class="stat-value">Hybrid (CBF + UBCF)</span>
                    </li>
                    <li class="stat-item">
                        <span class="stat-label">Responden Simulasi</span>
                        <span class="stat-value">150 Pengguna</span>
                    </li>
                    <li class="stat-item">
                        <span class="stat-label">Jumlah Kos</span>
                        <span class="stat-value">15 Terdaftar</span>
                    </li>
                </ul>
            </div>

            <!-- API Endpoints Card -->
            <div class="card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    REST API Utama
                </h2>
                <table class="endpoints-table">
                    <thead>
                        <tr>
                            <th>Metode</th>
                            <th>Endpoint</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="method post">POST</span></td>
                            <td><code>/api/register</code></td>
                            <td>Daftar Pengguna Baru</td>
                        </tr>
                        <tr>
                            <td><span class="method post">POST</span></td>
                            <td><code>/api/login</code></td>
                            <td>Login & Dapatkan Token</td>
                        </tr>
                        <tr>
                            <td><span class="method get">GET</span></td>
                            <td><code>/api/recommendations</code></td>
                            <td>Rekomendasi Adaptif</td>
                        </tr>
                        <tr>
                            <td><span class="method get">GET</span></td>
                            <td><code>/api/kos</code></td>
                            <td>Daftar Semua Kos</td>
                        </tr>
                        <tr>
                            <td><span class="method post">POST</span></td>
                            <td><code>/api/kos/{id}/rate</code></td>
                            <td>Rating (Warm-Start Trigger)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <footer>
            <p>Antarmuka KOSKITA dapat dijalankan melalui proyek Flutter Anda dengan perintah <code>flutter run</code>.</p>
            <p style="margin-top: 8px;">Dibuat untuk kebutuhan Skripsi Program Studi Sistem Informasi &copy; 2026</p>
        </footer>
    </div>
</body>
</html>

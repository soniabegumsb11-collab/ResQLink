
<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #101a3a, #263b70);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            color: white;
        }

        .error-box {
            max-width: 550px;
            padding: 45px 25px;
            text-align: center;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
        }

        .error-code {
            font-size: 100px;
            font-weight: bold;
            color: #ffc107;
        }

        .btn-home {
            background: #ffc107;
            color: #172554;
            font-weight: bold;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }

        .btn-home:hover {
            background: #e0a800;
            color: #172554;
        }
    </style>
</head>

<body>

    <div class="error-box">
        <div class="error-code">404</div>

        <h2>Page Not Found!</h2>

        <p class="mt-3">
            Sorry! The page you are looking for does not exist
            or may have been moved.
        </p>

        <a href="index.php" class="btn-home">
            Back to Home
        </a>
    </div>

</body>
</html>

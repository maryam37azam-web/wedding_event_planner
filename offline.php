<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="theme-color" content="#a4004d">

    <title>Offline | Wedding Event Planner</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f8edf2;
            color: #333333;
        }

        .offline-card {
            width: 100%;
            max-width: 470px;
            padding: 42px 30px;

            border-radius: 24px;

            background: #ffffff;

            text-align: center;

            box-shadow: 0 16px 45px rgba(0, 0, 0, 0.14);
        }

        .offline-icon {
            width: 82px;
            height: 82px;
            margin: 0 auto 22px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #f8edf2;
            color: #a4004d;

            font-size: 37px;
        }

        h1 {
            margin: 0 0 14px;
            color: #a4004d;
        }

        p {
            margin: 0 0 25px;
            color: #666666;
            line-height: 1.7;
        }

        button {
            padding: 13px 24px;

            border: none;
            border-radius: 25px;

            background: #a4004d;
            color: #ffffff;

            cursor: pointer;

            font-size: 15px;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <main class="offline-card">
        <div class="offline-icon">♡</div>

        <h1>You Are Offline</h1>

        <p>
            Wedding Event Planner cannot connect to the internet
            right now. Check your connection and try again.
        </p>

        <button type="button" onclick="window.location.reload();">
            Try Again
        </button>
    </main>
</body>
</html>
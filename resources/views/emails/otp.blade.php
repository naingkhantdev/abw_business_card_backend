<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $appName }} OTP Code</title>
    <style>
        body, table, td, p, a {
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #f4f1ed;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
        }

        img {
            border: 0;
            display: block;
            line-height: 100%;
            max-width: 100%;
        }

        .wrapper {
            width: 100%;
            background:
                radial-gradient(circle at top left, #fff0e4 0%, #f8f3ef 45%, #f2eeea 100%);
        }

        .outer {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }

        .preheader {
            display: none !important;
            visibility: hidden;
            opacity: 0;
            color: transparent;
            height: 0;
            width: 0;
            overflow: hidden;
            mso-hide: all;
        }

        .hero {
            padding: 36px 20px 18px;
        }

        .hero-card {
            border-radius: 32px 32px 0 0;
            background: linear-gradient(135deg, #1d1411 0%, #2d1a14 42%, #e96126 100%);
            overflow: hidden;
        }

        .brand-shell {
            padding: 32px 32px 20px;
        }

        .brand-badge {
            width: 68px;
            height: 68px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            text-align: center;
        }

        .brand-icon {
            width: 68px;
            height: 68px;
            padding: 10px;
            box-sizing: border-box;
        }

        .brand-fallback {
            width: 68px;
            height: 68px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            line-height: 68px;
            text-align: center;
            letter-spacing: 0.04em;
        }

        .eyebrow {
            margin: 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 11px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .hero-title {
            margin: 14px 0 10px;
            color: #ffffff;
            font-size: 34px;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .hero-copy {
            margin: 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 15px;
            line-height: 1.75;
            max-width: 460px;
        }

        .panel-wrap {
            padding: 0 20px 36px;
        }

        .panel {
            background: #ffffff;
            border-radius: 0 0 32px 32px;
            box-shadow: 0 28px 64px rgba(37, 22, 16, 0.12);
            overflow: hidden;
        }

        .panel-inner {
            padding: 34px 32px 30px;
        }

        .section-label {
            margin: 0 0 10px;
            color: #d65b22;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .section-title {
            margin: 0 0 12px;
            color: #17110f;
            font-size: 26px;
            line-height: 1.25;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .section-copy {
            margin: 0;
            color: #65544d;
            font-size: 15px;
            line-height: 1.8;
        }

        .otp-shell {
            padding: 24px 0 22px;
        }

        .otp-card {
            border: 1px solid #f0e2d9;
            border-radius: 24px;
            background: linear-gradient(180deg, #fff8f4 0%, #fff2ea 100%);
        }

        .otp-card-inner {
            padding: 24px 22px;
            text-align: center;
        }

        .otp-label {
            margin: 0 0 10px;
            color: #93624c;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .otp-code {
            margin: 0;
            color: #e75b1f;
            font-size: 40px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.28em;
            text-indent: 0.28em;
        }

        .divider {
            height: 1px;
            background: #f0e8e3;
            margin: 6px 0 0;
        }

        .detail-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f3ece7;
            font-size: 14px;
            line-height: 1.6;
        }

        .detail-table tr:last-child td {
            border-bottom: none;
        }

        .detail-key {
            color: #8b766d;
            width: 120px;
            vertical-align: top;
        }

        .detail-value {
            color: #231915;
            font-weight: 600;
        }

        .note {
            margin: 22px 0 0;
            padding: 16px 18px;
            border-radius: 18px;
            background: #f8f5f2;
            color: #6d5b53;
            font-size: 13px;
            line-height: 1.75;
        }

        .footer {
            padding: 0 20px 34px;
        }

        .footer-copy {
            margin: 0;
            text-align: center;
            color: #8a7a73;
            font-size: 12px;
            line-height: 1.8;
        }

        @media only screen and (max-width: 600px) {
            .hero {
                padding: 0;
            }

            .panel-wrap {
                padding: 0 0 20px;
            }

            .hero-card,
            .panel {
                border-radius: 0;
            }

            .brand-shell,
            .panel-inner {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .brand-shell {
                padding-top: 28px !important;
                padding-bottom: 18px !important;
            }

            .panel-inner {
                padding-top: 28px !important;
                padding-bottom: 26px !important;
            }

            .hero-title {
                font-size: 28px !important;
            }

            .section-title {
                font-size: 22px !important;
            }

            .otp-code {
                font-size: 32px !important;
                letter-spacing: 0.2em !important;
                text-indent: 0.2em !important;
            }

            .detail-key,
            .detail-value {
                display: block;
                width: 100%;
            }

            .detail-key {
                padding-bottom: 2px !important;
            }
        }
    </style>
</head>
<body>
    <div class="preheader">Your {{ $appName }} verification code is {{ $otp }}. This code expires in 5 minutes.</div>

    <table role="presentation" width="100%" class="wrapper">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" class="outer">
                    <tr>
                        <td class="hero">
                            <table role="presentation" width="100%" class="hero-card">
                                <tr>
                                    <td class="brand-shell">
                                        <table role="presentation" width="100%">
                                            <tr>
                                                <td align="left">
                                                    <table role="presentation" class="brand-badge">
                                                        <tr>
                                                            <td class="brand-icon" align="center" valign="middle">
                                                                <img src="{{ $iconUrl }}" alt="{{ $appName }} icon">
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top: 22px;">
                                                    <p class="eyebrow">Secure Access</p>
                                                    <h1 class="hero-title">{{ $appName }} verification code</h1>
                                                    <p class="hero-copy">
                                                        Confirm your identity with the one-time password below to continue securely in the app.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="panel-wrap">
                            <table role="presentation" width="100%" class="panel">
                                <tr>
                                    <td class="panel-inner">
                                        <p class="section-label">One-Time Password</p>
                                        <h2 class="section-title">Use this code to complete your sign in</h2>
                                        <p class="section-copy">
                                            Enter the verification code below for <strong>{{ $email }}</strong>. For your security, this code can only be used once.
                                        </p>

                                        <table role="presentation" width="100%" class="otp-shell">
                                            <tr>
                                                <td>
                                                    <table role="presentation" width="100%" class="otp-card">
                                                        <tr>
                                                            <td class="otp-card-inner">
                                                                <p class="otp-label">Verification Code</p>
                                                                <p class="otp-code">{{ $otp }}</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <div class="divider"></div>

                                        <table role="presentation" width="100%" class="detail-table">
                                            <tr>
                                                <td class="detail-key">Account</td>
                                                <td class="detail-value">{{ $email }}</td>
                                            </tr>
                                            <tr>
                                                <td class="detail-key">Valid for</td>
                                                <td class="detail-value">5 minutes</td>
                                            </tr>
                                            <tr>
                                                <td class="detail-key">Requested from</td>
                                                <td class="detail-value">{{ $appName }}</td>
                                            </tr>
                                        </table>

                                        <p class="note">
                                            If you did not request this code, you can ignore this message. No changes will be made to your account unless this verification code is entered in the app.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <p class="footer-copy">
                                {{ $appName }}<br>
                                This is an automated security message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

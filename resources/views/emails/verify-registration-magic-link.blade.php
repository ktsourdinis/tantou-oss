<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Complete your Tantou workspace registration') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #020617; color: #f8fafc; padding: 40px 20px; margin: 0;">
    <div style="max-width: 560px; margin: 0 auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; padding: 36px 32px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);">
        <div style="margin-bottom: 24px;">
            <span style="display: inline-block; width: 44px; height: 44px; line-height: 44px; text-align: center; border-radius: 12px; background: linear-gradient(135deg, #4f46e5, #f59e0b); color: #ffffff; font-weight: bold; font-size: 20px;">
                担
            </span>
            <span style="font-size: 20px; font-weight: bold; color: #ffffff; margin-left: 10px; vertical-align: middle;">
                Tantou <span style="font-size: 11px; padding: 2px 6px; border-radius: 9999px; background-color: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3);">担当</span>
            </span>
        </div>

        <h1 style="font-size: 20px; font-weight: 700; color: #ffffff; margin-top: 0; margin-bottom: 12px;">
            {{ __('Finish setting up your workspace') }}
        </h1>

        <p style="font-size: 14px; line-height: 1.6; color: #94a3b8; margin-bottom: 24px;">
            {{ __('We received a request to create a Tantou workspace for') }} <strong style="color: #f1f5f9;">{{ $emailAddress }}</strong>. {{ __('Click the secure verification button below to set up your organization and admin password.') }}
        </p>

        <div style="text-align: center; margin: 32px 0;">
            <a href="{{ $verificationUrl }}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 14px 28px; border-radius: 12px; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);">
                {{ __('Verify & Complete Registration') }} &rarr;
            </a>
        </div>

        <p style="font-size: 12px; line-height: 1.5; color: #64748b; margin-top: 24px; padding-top: 20px; border-top: 1px solid #1e293b;">
            ⏱️ {{ __('This magic link expires in 30 minutes and can only be used once.') }}<br>
            {{ __('If you did not request this, you can safely ignore this email.') }}
        </p>

        <p style="font-size: 11px; color: #475569; word-break: break-all; margin-top: 16px;">
            {{ __('Direct link:') }} <a href="{{ $verificationUrl }}" style="color: #6366f1;">{{ $verificationUrl }}</a>
        </p>
    </div>
</body>
</html>

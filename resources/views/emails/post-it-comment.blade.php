<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('New Comment on :ref', ['ref' => $taskReference]) }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #020617; color: #f8fafc; padding: 40px 20px; margin: 0;">
    <div style="max-width: 580px; margin: 0 auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; padding: 36px 32px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);">
        <!-- Brand Header -->
        <div style="display: flex; align-items: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #1e293b;">
            <span style="display: inline-block; width: 44px; height: 44px; line-height: 44px; text-align: center; border-radius: 12px; background: linear-gradient(135deg, #4f46e5, #06b6d4); color: #ffffff; font-weight: bold; font-size: 20px;">
                担
            </span>
            <div style="margin-left: 12px;">
                <div style="font-size: 18px; font-weight: bold; color: #ffffff;">
                    {{ $orgName }}
                </div>
                <div style="font-size: 11px; color: #64748b; font-family: monospace;">
                    Tantou 担当 Human Capacity OS
                </div>
            </div>
        </div>

        <!-- Notification Subheader & Ref Badge -->
        <div style="margin-bottom: 16px;">
            <span style="display: inline-block; font-size: 11px; font-family: monospace; font-weight: 700; color: #818cf8; background-color: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); padding: 3px 8px; border-radius: 6px;">
                {{ $taskReference }}
            </span>
            <span style="display: inline-block; font-size: 12px; color: #94a3b8; margin-left: 8px;">
                {{ __('New Comment Added') }}
            </span>
        </div>

        <!-- Task Title -->
        <h1 style="font-size: 19px; font-weight: 700; color: #ffffff; margin-top: 0; margin-bottom: 20px; line-height: 1.4;">
            {{ $postIt->title }}
        </h1>

        <!-- Comment Body Box -->
        <div style="background-color: #1e293b; border-left: 4px solid #6366f1; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
            <div style="font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; display: flex; align-items: center;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background-color: #818cf8; margin-right: 8px;"></span>
                {{ $authorName }}
            </div>
            <p style="font-size: 14px; line-height: 1.6; color: #f1f5f9; margin: 0; white-space: pre-wrap;">{{ $comment->body }}</p>
        </div>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 32px 0 24px 0;">
            <a href="{{ $actionUrl }}" style="display: inline-block; background: linear-gradient(135deg, #4f46e5, #6366f1); color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 14px 28px; border-radius: 12px; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);">
                {{ __('View Request & Reply') }} &rarr;
            </a>
        </div>

        <!-- Footer Note -->
        <p style="font-size: 12px; line-height: 1.5; color: #64748b; margin-top: 24px; padding-top: 20px; border-top: 1px solid #1e293b; text-align: center;">
            {{ __('You received this notification because you are a collaborator or the requester for this task.') }}<br>
            {{ __('Direct Link:') }} <a href="{{ $actionUrl }}" style="color: #818cf8; word-break: break-all;">{{ $actionUrl }}</a>
        </p>
    </div>
</body>
</html>

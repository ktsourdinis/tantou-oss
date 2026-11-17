<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $resolutionType === 'canceled' ? __('Request Canceled: :ref', ['ref' => $taskReference]) : __('Request Completed: :ref', ['ref' => $taskReference]) }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #020617; color: #f8fafc; padding: 40px 20px; margin: 0;">
    <div style="max-width: 580px; margin: 0 auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; padding: 36px 32px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);">
        <!-- Brand Header -->
        <div style="display: flex; align-items: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #1e293b;">
            <span style="display: inline-block; width: 44px; height: 44px; line-height: 44px; text-align: center; border-radius: 12px; background: linear-gradient(135deg, {{ $resolutionType === 'canceled' ? '#f43f5e, #fb7185' : '#10b981, #06b6d4' }}); color: #ffffff; font-weight: bold; font-size: 20px;">
                {{ $resolutionType === 'canceled' ? '✕' : '✓' }}
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
            @if ($resolutionType === 'canceled')
                <span style="display: inline-block; font-size: 12px; font-weight: 700; color: #fb7185; background-color: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); padding: 3px 8px; border-radius: 6px; margin-left: 6px;">
                    🚫 {{ __('CANCELED') }}
                </span>
            @else
                <span style="display: inline-block; font-size: 12px; font-weight: 700; color: #34d399; background-color: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); padding: 3px 8px; border-radius: 6px; margin-left: 6px;">
                    ✅ {{ __('COMPLETED') }}
                </span>
            @endif
        </div>

        <!-- Task Title -->
        <h1 style="font-size: 19px; font-weight: 700; color: #ffffff; margin-top: 0; margin-bottom: 12px; line-height: 1.4;">
            {{ $postIt->title }}
        </h1>

        <!-- Status Details -->
        <p style="font-size: 14px; line-height: 1.6; color: #94a3b8; margin-top: 0; margin-bottom: 24px;">
            @if ($resolutionType === 'canceled')
                {{ __('Your request has been canceled by :actor.', ['actor' => $actorName]) }}
            @else
                {{ __('Your request has been successfully resolved and marked as completed by :actor.', ['actor' => $actorName]) }}
            @endif
        </p>

        <!-- Resolution Note / Comment Box if any -->
        @if (!empty($latestComment))
            <div style="background-color: #1e293b; border-left: 4px solid {{ $resolutionType === 'canceled' ? '#f43f5e' : '#10b981' }}; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 6px;">
                    {{ __('Resolution Note / Latest Comment') }}
                </div>
                <p style="font-size: 14px; line-height: 1.6; color: #f1f5f9; margin: 0; white-space: pre-wrap;">{{ $latestComment }}</p>
            </div>
        @endif

        <!-- CTA Button -->
        <div style="text-align: center; margin: 32px 0 24px 0;">
            <a href="{{ $actionUrl }}" style="display: inline-block; background: linear-gradient(135deg, {{ $resolutionType === 'canceled' ? '#e11d48, #f43f5e' : '#059669, #10b981' }}); color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 14px 28px; border-radius: 12px; box-shadow: 0 4px 14px {{ $resolutionType === 'canceled' ? 'rgba(225, 29, 72, 0.4)' : 'rgba(5, 150, 105, 0.4)' }};">
                {{ __('View Timeline & Details') }} &rarr;
            </a>
        </div>

        <!-- Footer Note -->
        <p style="font-size: 12px; line-height: 1.5; color: #64748b; margin-top: 24px; padding-top: 20px; border-top: 1px solid #1e293b; text-align: center;">
            {{ __('Thank you for using :org!', ['org' => $orgName]) }}<br>
            {{ __('Direct Link:') }} <a href="{{ $actionUrl }}" style="color: #818cf8; word-break: break-all;">{{ $actionUrl }}</a>
        </p>
    </div>
</body>
</html>

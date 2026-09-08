<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ config('app.name') }}</title>
    <style>
        p { margin: 0 0 16px 0; font-family: sans-serif; font-size: 16px; line-height: 1.6; color: {{ $colors['text_primary'] }}; }
        h1 { font-family: sans-serif; font-size: 22px; font-weight: 700; color: {{ $colors['text_heading'] }}; margin: 0 0 16px 0; line-height: 1.3; }
        h2 { font-family: sans-serif; font-size: 18px; font-weight: 700; color: {{ $colors['text_heading'] }}; margin: 0 0 12px 0; line-height: 1.3; }
        h3 { font-family: sans-serif; font-size: 16px; font-weight: 700; color: {{ $colors['text_heading'] }}; margin: 0 0 8px 0; line-height: 1.3; }
        ul { margin: 0 0 16px 0; padding-left: 24px; font-family: sans-serif; font-size: 16px; line-height: 1.6; color: {{ $colors['text_primary'] }}; }
        ol { margin: 0 0 16px 0; padding-left: 24px; font-family: sans-serif; font-size: 16px; line-height: 1.6; color: {{ $colors['text_primary'] }}; }
        li { margin-bottom: 4px; }
        a { color: {{ $colors['text_link'] }}; text-decoration: underline; }
        strong { font-weight: 700; }
        em { font-style: italic; }
        blockquote { margin: 0 0 16px 0; padding: 8px 16px; border-left: 4px solid {{ $colors['border'] }}; color: {{ $colors['text_secondary'] }}; font-style: italic; }
    </style>
</head>
<body style="margin:0;padding:0;">
    <div style="background-color:{{ $colors['page'] }};margin:0;padding:0;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:{{ $colors['page'] }};">
        <tr><td style="padding:32px 16px;">

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="600" style="max-width:600px;width:100%;">
                <tr><td style="border:1px solid {{ $colors['border'] }};border-radius:8px;background-color:{{ $colors['surface'] }};">

                    {{-- Header --}}
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="background-color:{{ $colors['brand_strong'] }};padding:20px 32px;border-radius:7px 7px 0 0;">
                                <span style="font-family:sans-serif;font-size:20px;font-weight:700;color:{{ $colors['on_brand'] }};">
                                    {{ config('app.name') }}
                                </span>
                            </td>
                        </tr>
                    </table>

                    {{-- Body --}}
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="padding:32px;font-family:sans-serif;font-size:16px;line-height:1.6;color:{{ $colors['text_primary'] }};">
                                @yield('content')
                            </td>
                        </tr>
                    </table>

                    {{-- Footer --}}
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="background-color:{{ $colors['surface_secondary'] }};border-top:1px solid {{ $colors['border'] }};padding:16px 32px;text-align:center;font-family:sans-serif;font-size:12px;color:{{ $colors['text_secondary'] }};border-radius:0 0 7px 7px;">
                                <p style="margin:0 0 4px 0;">
                                    <a href="{{ config('app.url') }}"
                                       style="color:{{ $colors['text_secondary'] }};text-decoration:none;">{{ config('app.url') }}</a>
                                </p>
                                <p style="margin:0;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>

                </td></tr>
            </table>

        </td></tr>
    </table>
    </div>
</body>
</html>

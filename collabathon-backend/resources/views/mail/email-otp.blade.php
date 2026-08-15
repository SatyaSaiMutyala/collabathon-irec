{{--
    Sign-in code email — same table-layout, inline-style reasoning as mail.broker-approved:
    Outlook and most mobile clients strip <style> blocks and don't do flex/grid.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — your sign-in code</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f7;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your {{ $appName }} sign-in code is {{ $code }}. It expires in {{ $minutes }} minutes.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f5f5f7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:420px; background-color:#ffffff; border:1px solid #d2d2d7;">

                    <tr>
                        <td style="background-color:#1d1d1f; padding:22px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:17px; font-weight:600; color:#ffffff; letter-spacing:-0.01em;">
                                {{ $appName }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 28px 8px; text-align:center;">
                            <p style="margin:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:13.5px; color:#3a3a3c;">
                                Your sign-in code is
                            </p>
                            <p style="margin:0; font-family:'SF Mono',Menlo,Consolas,monospace;
                                      font-size:36px; font-weight:700; color:#1d1d1f; letter-spacing:0.12em;">
                                {{ $code }}
                            </p>
                            <p style="margin:14px 0 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12.5px; color:#6e6e73;">
                                Expires in {{ $minutes }} minutes. Enter it in the app to continue.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top:1px solid #e8e8ed; padding:18px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; line-height:1.6; color:#86868b;">
                                If you did not request this code, you can ignore this email — nobody can sign in
                                without also having access to this inbox.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                          font-size:11.5px; color:#86868b;">
                    &copy; {{ date('Y') }} {{ $appName }}. This is an automated message.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

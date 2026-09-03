{{--
    Broker-interest notification for the developer — same table-layout/inline-style
    reasoning as mail/broker-approved.blade.php. Deliberately short: this is a "come
    look at the app" nudge, not the request itself.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — new broker request</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f7;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $brokerName }} sent a request for {{ $propertyName }}.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f5f5f7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:560px; background-color:#ffffff; border:1px solid #d2d2d7;">

                    <tr>
                        <td style="background-color:#1d1d1f; padding:22px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:17px; font-weight:600; color:#ffffff; letter-spacing:-0.01em;">
                                {{ $appName }}
                            </p>
                            <p style="margin:3px 0 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12.5px; color:#98989d;">
                                Developer partner network
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 8px;">
                            <p style="margin:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; font-weight:600; letter-spacing:0.08em;
                                      text-transform:uppercase; color:#b55500;">
                                New request
                            </p>
                            <h1 style="margin:0 0 14px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                       font-size:22px; font-weight:600; color:#1d1d1f; letter-spacing:-0.02em;">
                                @if($developerName)Hi {{ $developerName }}, @endif
                                a broker is interested in {{ $propertyName }}
                            </h1>
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:14.5px; line-height:1.65; color:#3a3a3c;">
                                <strong style="color:#1d1d1f;">{{ $brokerName }}</strong> sent a request for
                                <strong style="color:#1d1d1f;">{{ $propertyName }}</strong>. Open the
                                {{ $appName }} app to review their profile and accept or decline the
                                request — their contact details unlock as soon as you do.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 28px 30px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:13.5px; line-height:1.6; color:#6e6e73;">
                                Sign in to the {{ $appName }} app to view and respond to this request.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top:1px solid #e8e8ed; padding:18px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; line-height:1.6; color:#86868b;">
                                You're receiving this because a channel partner sent a request on one of
                                your listings.
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

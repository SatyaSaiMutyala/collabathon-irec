{{--
    Rejection / access-revoked email — same table-layout/inline-style reasoning as
    mail/broker-approved.blade.php.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — {{ $wasRevoked ? 'access paused' : 'registration update' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f7;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $wasRevoked ? 'Your access has been paused.' : 'An update on your registration.' }}
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
                                Channel partner network
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 8px;">
                            <p style="margin:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; font-weight:600; letter-spacing:0.08em;
                                      text-transform:uppercase; color:#b3261e;">
                                {{ $wasRevoked ? 'Access paused' : 'Not approved' }}
                            </p>
                            <h1 style="margin:0 0 14px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                       font-size:22px; font-weight:600; color:#1d1d1f; letter-spacing:-0.02em;">
                                Hi {{ $name }}
                            </h1>
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:14.5px; line-height:1.65; color:#3a3a3c;">
                                @if($wasRevoked)
                                    An admin has paused your access to {{ $appName }}. You will not be able
                                    to sign in until this is resolved.
                                @else
                                    An admin has reviewed your registration and was not able to approve it
                                    at this time.
                                @endif
                            </p>
                        </td>
                    </tr>

                    {{-- Reason block ------------------------------------------------ --}}
                    <tr>
                        <td style="padding:22px 28px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background-color:#f5f5f7; border:1px solid #e8e8ed;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 8px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                  font-size:12px; font-weight:600; letter-spacing:0.06em;
                                                  text-transform:uppercase; color:#6e6e73;">
                                            Reason
                                        </p>
                                        <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                  font-size:14.5px; line-height:1.6; color:#1d1d1f;">
                                            {{ $reason }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 28px 30px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:13.5px; line-height:1.6; color:#6e6e73;">
                                If you believe this is a mistake, or would like to discuss it, reply to
                                this email and we'll follow up.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top:1px solid #e8e8ed; padding:18px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; line-height:1.6; color:#86868b;">
                                @if($wasRevoked)
                                    Any sessions signed in on your account have been ended.
                                @else
                                    You're welcome to update your details and submit a new registration.
                                @endif
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

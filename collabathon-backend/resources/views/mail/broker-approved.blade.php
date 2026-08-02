{{--
    Approval email.

    Table layout with inline styles, deliberately: Outlook and most mobile clients strip
    <style> blocks and do not implement flex or grid, so anything laid out the way the
    admin panel is would arrive as a stack of unstyled paragraphs. Fonts stay on the
    system stack — a webfont is another request a mail client will refuse.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — account approved</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f7;">

    {{-- Shown in the inbox list under the subject, before the mail is opened. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your registration has been approved — here is how to sign in.
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
                                      text-transform:uppercase; color:#157347;">
                                Approved
                            </p>
                            <h1 style="margin:0 0 14px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                       font-size:22px; font-weight:600; color:#1d1d1f; letter-spacing:-0.02em;">
                                You're in, {{ $name }}
                            </h1>
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:14.5px; line-height:1.65; color:#3a3a3c;">
                                {{-- A space before @if is required, not cosmetic: Blade only treats
                                     `@` as a directive when it does not directly follow a word
                                     character, so `registration@if(...)` compiles the @endif alone
                                     and the template fails to parse. --}}
                                Your registration @if($company)for <strong style="color:#1d1d1f;">{{ $company }}</strong> @endif
                                has been reviewed and approved. You can now sign in to the {{ $appName }} app,
                                browse live inventory from our developers, and register your interest in any project.
                            </p>
                        </td>
                    </tr>

                    {{-- Sign-in block ------------------------------------------------ --}}
                    <tr>
                        <td style="padding:22px 28px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background-color:#f5f5f7; border:1px solid #e8e8ed;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 14px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                  font-size:12px; font-weight:600; letter-spacing:0.06em;
                                                  text-transform:uppercase; color:#6e6e73;">
                                            Your sign-in details
                                        </p>

                                        <p style="margin:0 0 3px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                  font-size:12.5px; color:#6e6e73;">Email</p>
                                        <p style="margin:0 0 14px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                  font-size:15px; font-weight:600; color:#1d1d1f; word-break:break-all;">
                                            {{ $email }}
                                        </p>

                                        <p style="margin:0 0 3px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                  font-size:12.5px; color:#6e6e73;">Password</p>
                                        @if($password)
                                            <p style="margin:0; font-family:'SF Mono',Menlo,Consolas,monospace;
                                                      font-size:16px; font-weight:600; color:#1d1d1f;
                                                      letter-spacing:0.04em; background-color:#ffffff;
                                                      border:1px solid #d2d2d7; padding:9px 12px; display:inline-block;">
                                                {{ $password }}
                                            </p>
                                            <p style="margin:10px 0 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                      font-size:12.5px; line-height:1.55; color:#96701a;">
                                                This password was issued for you. Please change it after your first sign-in.
                                            </p>
                                        @else
                                            {{-- No plaintext exists on a normal approval: the broker chose their own
                                                 password at registration and it is stored hashed. Saying so is better
                                                 than inventing a credential that would not work. --}}
                                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                      font-size:14.5px; color:#3a3a3c; line-height:1.6;">
                                                The password you chose when you registered.
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 28px 4px;">
                            <p style="margin:0 0 10px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:14px; font-weight:600; color:#1d1d1f;">
                                What you can do now
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                @foreach([
                                    'Browse every live project from our developer partners',
                                    'Register interest and unlock the developer\'s contact details',
                                    'Track each request from interest through to acceptance',
                                    'Read the channel partner terms attached to each project',
                                ] as $item)
                                    <tr>
                                        <td valign="top" style="padding:0 8px 8px 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                                font-size:14px; color:#b55500;">&bull;</td>
                                        <td style="padding:0 0 8px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                   font-size:14px; line-height:1.55; color:#3a3a3c;">{{ $item }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 28px 30px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:13.5px; line-height:1.6; color:#6e6e73;">
                                Open the {{ $appName }} app on your phone and sign in with the details above.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top:1px solid #e8e8ed; padding:18px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; line-height:1.6; color:#86868b;">
                                Treat these details as confidential — anyone with them can sign in as you.
                                If you did not register with {{ $appName }}, reply to this email and we'll close the account.
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

{{--
    New-project-assignment email — full project sheet plus one-click Accept/Decline.

    Table layout with inline styles, same reasoning as mail.broker-approved: Outlook and
    most mobile mail clients strip <style> blocks and don't do flex/grid, so anything laid
    out the way the admin panel is would arrive as unstyled paragraphs.

    Both action links are signed, expiring GET URLs that land on a confirmation page —
    they do not act on click. See DeveloperProjectResponseController for why.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — new project for your review</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f7;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $property->name }} is ready for your review — accept or decline right from this email.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f5f5f7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px; background-color:#ffffff; border:1px solid #d2d2d7;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#1d1d1f; padding:22px 28px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:17px; font-weight:600; color:#ffffff; letter-spacing:-0.01em;">
                                {{ $appName }}
                            </p>
                            <p style="margin:3px 0 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12.5px; color:#98989d;">
                                Developer inventory
                            </p>
                        </td>
                    </tr>

                    {{-- Intro --}}
                    <tr>
                        <td style="padding:30px 28px 6px;">
                            <p style="margin:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; font-weight:600; letter-spacing:0.08em;
                                      text-transform:uppercase; color:#b55500;">
                                New project for your review
                            </p>
                            <h1 style="margin:0 0 10px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                       font-size:22px; font-weight:600; color:#1d1d1f; letter-spacing:-0.02em;">
                                {{ $property->name }}
                            </h1>
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:14.5px; line-height:1.65; color:#3a3a3c;">
                                An admin has published this project under
                                <strong style="color:#1d1d1f;">{{ $property->developer?->company_name }}</strong>.
                                Review the details below and accept or decline — accepting makes it visible to
                                channel partners in the {{ $appName }} app immediately.
                            </p>
                        </td>
                    </tr>

                    @if($coverImageUrl)
                        <tr>
                            <td style="padding:18px 28px 0;">
                                <img src="{{ $coverImageUrl }}" width="544" alt="{{ $property->name }}"
                                     style="width:100%; max-width:544px; height:auto; display:block; border:1px solid #e8e8ed;">
                            </td>
                        </tr>
                    @endif

                    {{-- CTA buttons --}}
                    <tr>
                        <td style="padding:22px 28px 4px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td width="50%" style="padding-right:6px;">
                                        <a href="{{ $acceptUrl }}"
                                           style="display:block; text-align:center; background-color:#157347; color:#ffffff;
                                                  font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px;
                                                  font-weight:600; text-decoration:none; padding:12px 0; border-radius:6px;">
                                            Accept project
                                        </a>
                                    </td>
                                    <td width="50%" style="padding-left:6px;">
                                        <a href="{{ $declineUrl }}"
                                           style="display:block; text-align:center; background-color:#ffffff; color:#b42318;
                                                  font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px;
                                                  font-weight:600; text-decoration:none; padding:11px 0; border-radius:6px;
                                                  border:1px solid #d2d2d7;">
                                            Decline
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:10px 0 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:11.5px; color:#86868b; text-align:center;">
                                Either link opens a confirmation page first — nothing changes until you confirm there.
                            </p>
                        </td>
                    </tr>

                    {{-- Key facts --}}
                    <tr>
                        <td style="padding:24px 28px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background-color:#f5f5f7; border:1px solid #e8e8ed;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            @foreach([
                                                ['Type', $property->project_type],
                                                ['Stage', $property->project_status],
                                                ['Location', trim(($property->locality ?? '') . ', ' . ($property->city ?? ''), ', ')],
                                                ['RERA number', $property->rera_number],
                                                ['Price range', $property->price_min ? ($property->currency . ' ' . number_format($property->price_min) . ' – ' . number_format($property->price_max)) : null],
                                                ['Possession', optional($property->possession_date)->format('M Y')],
                                            ] as [$label, $value])
                                                @if($value)
                                                    <tr>
                                                        <td style="padding:0 0 10px; width:40%; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                                   font-size:12.5px; color:#6e6e73; vertical-align:top;">{{ $label }}</td>
                                                        <td style="padding:0 0 10px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                                   font-size:13.5px; font-weight:600; color:#1d1d1f;">{{ $value }}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Unit types --}}
                    @if($unitTypes->isNotEmpty())
                        <tr>
                            <td style="padding:24px 28px 0;">
                                <p style="margin:0 0 10px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                          font-size:14px; font-weight:600; color:#1d1d1f;">
                                    Unit types
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                   font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:#86868b; border-bottom:1px solid #e8e8ed;">Type</td>
                                        <td style="padding:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                   font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:#86868b; border-bottom:1px solid #e8e8ed;">Carpet area</td>
                                        <td style="padding:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                   font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:#86868b; border-bottom:1px solid #e8e8ed; text-align:right;">Price</td>
                                    </tr>
                                    @foreach($unitTypes as $unit)
                                        <tr>
                                            <td style="padding:8px 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:13px; color:#1d1d1f; border-bottom:1px solid #f0f0f2;">{{ $unit->label }}</td>
                                            <td style="padding:8px 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:13px; color:#3a3a3c; border-bottom:1px solid #f0f0f2;">{{ $unit->carpet_area_sqft ? number_format($unit->carpet_area_sqft) . ' sqft' : '—' }}</td>
                                            <td style="padding:8px 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:13px; color:#3a3a3c; border-bottom:1px solid #f0f0f2; text-align:right;">{{ $property->currency }} {{ number_format($unit->price_min) }}{{ $unit->price_max && $unit->price_max != $unit->price_min ? ' – ' . number_format($unit->price_max) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Connectivity / nearby infrastructure --}}
                    @if($detail && (filled($detail->connectivity_highlights) || filled($detail->nearby_infrastructure)))
                        <tr>
                            <td style="padding:24px 28px 0;">
                                <p style="margin:0 0 10px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                          font-size:14px; font-weight:600; color:#1d1d1f;">
                                    Location &amp; connectivity
                                </p>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    @foreach(array_merge($detail->connectivity_highlights ?? [], $detail->nearby_infrastructure ?? []) as $item)
                                        <tr>
                                            <td valign="top" style="padding:0 8px 6px 0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                                    font-size:13px; color:#b55500;">&bull;</td>
                                            <td style="padding:0 0 6px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                       font-size:13.5px; line-height:1.5; color:#3a3a3c;">{{ $item }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Amenities --}}
                    @if($detail && filled($detail->amenities))
                        <tr>
                            <td style="padding:24px 28px 0;">
                                <p style="margin:0 0 10px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                          font-size:14px; font-weight:600; color:#1d1d1f;">
                                    Amenities
                                </p>
                                <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                          font-size:13.5px; line-height:1.7; color:#3a3a3c;">
                                    {{ implode(' · ', $detail->amenities) }}
                                </p>
                            </td>
                        </tr>
                    @endif

                    {{-- Commercial terms --}}
                    @if($detail && ($detail->cp_commission_percent || $detail->fos_commission_percent))
                        <tr>
                            <td style="padding:22px 28px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                       style="background-color:#fff9ec; border:1px solid #f3e4bd;">
                                    <tr>
                                        <td style="padding:16px 20px;">
                                            <p style="margin:0 0 4px; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                                      font-size:12px; font-weight:600; letter-spacing:0.06em; text-transform:uppercase; color:#96701a;">
                                                Commercial terms
                                            </p>
                                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:13.5px; color:#3a3a3c;">
                                                @if($detail->cp_commission_percent)
                                                    CP commission: <strong style="color:#1d1d1f;">{{ $detail->cp_commission_percent }}%</strong>
                                                @endif
                                                @if($detail->cp_commission_percent && $detail->fos_commission_percent)
                                                    &nbsp;&middot;&nbsp;
                                                @endif
                                                @if($detail->fos_commission_percent)
                                                    FOS commission: <strong style="color:#1d1d1f;">{{ $detail->fos_commission_percent }}%</strong>
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Repeat CTA --}}
                    <tr>
                        <td style="padding:26px 28px 6px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td width="50%" style="padding-right:6px;">
                                        <a href="{{ $acceptUrl }}"
                                           style="display:block; text-align:center; background-color:#157347; color:#ffffff;
                                                  font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px;
                                                  font-weight:600; text-decoration:none; padding:12px 0; border-radius:6px;">
                                            Accept project
                                        </a>
                                    </td>
                                    <td width="50%" style="padding-left:6px;">
                                        <a href="{{ $declineUrl }}"
                                           style="display:block; text-align:center; background-color:#ffffff; color:#b42318;
                                                  font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; font-size:14px;
                                                  font-weight:600; text-decoration:none; padding:11px 0; border-radius:6px;
                                                  border:1px solid #d2d2d7;">
                                            Decline
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top:1px solid #e8e8ed; padding:18px 28px; margin-top:10px;">
                            <p style="margin:0; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                                      font-size:12px; line-height:1.6; color:#86868b;">
                                You can also review and respond to this project from the {{ $appName }} developer app,
                                under Pending projects. Both places update the same record.
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

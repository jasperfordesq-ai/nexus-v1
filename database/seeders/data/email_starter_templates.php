<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    [
        'name' => 'Announcement',
        'description' => 'A bold headline announcement with a hero image and a single call to action.',
        'category' => 'starter',
        'content_format' => 'html',
        'thumbnail' => null,
        'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Announcement</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  @media (max-width: 600px) {
    .email-container { width: 100% !important; }
    .stack-padding { padding-left: 20px !important; padding-right: 20px !important; }
    .hero-img { height: auto !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#F3EEE6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">A special announcement from {{tenant_name}}.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3EEE6;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
        <tr>
          <td align="center" style="background-color:#C1622D; padding:28px 20px;">
            <span style="font-family:Georgia,'Times New Roman',serif; font-size:22px; color:#FFFFFF; letter-spacing:0.5px;">{{tenant_name}}</span>
          </td>
        </tr>
        <tr>
          <td>
            <img src="https://via.placeholder.com/600x240" width="600" height="240" alt="" class="hero-img" style="display:block; width:100%; max-width:600px; height:auto; border:0;">
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:36px 40px 8px 40px;">
            <p style="margin:0 0 8px 0; font-family:Georgia,'Times New Roman',serif; font-size:20px; color:#3A3530;">Hi {{first_name}},</p>
            <h1 style="margin:0 0 20px 0; font-family:Georgia,'Times New Roman',serif; font-size:30px; line-height:1.25; color:#3A3530; font-weight:normal;">We have some exciting news to share</h1>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 40px 8px 40px;">
            <p style="margin:0 0 18px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#5C574D;">
              Starting [Month] [Day], {{tenant_name}} is rolling out something we think you'll love. It's the result of listening closely to our community and building something that makes it easier to connect, share, and give back.
            </p>
            <p style="margin:0 0 8px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#5C574D;">
              We can't wait for you to try it out and tell us what you think.
            </p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" align="center" style="padding:28px 40px 40px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#C1622D" style="border-radius:8px;">
                  <a href="#" target="_blank" style="display:inline-block; padding:14px 32px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">See What's New</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="border-top:1px solid #F3EEE6; font-size:1px; line-height:1px;">&nbsp;</td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:24px 40px 36px 40px; background-color:#FBF6EF;">
            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#5C574D;">
              You're receiving this email because you're a member of {{tenant_name}}.<br>
              <a href="{{unsubscribe_url}}" style="color:#C1622D; text-decoration:underline;">Unsubscribe from these emails</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML,
    ],
    [
        'name' => 'Community Digest',
        'description' => 'A roundup layout with three short sections, a stats strip, and a closing call to action.',
        'category' => 'starter',
        'content_format' => 'html',
        'thumbnail' => null,
        'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Community Digest</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  @media (max-width: 600px) {
    .email-container { width: 100% !important; }
    .stack-padding { padding-left: 20px !important; padding-right: 20px !important; }
    .stat-cell { display:block !important; width:100% !important; padding-bottom:16px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#F3EEE6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">Your {{tenant_name}} community digest is here.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3EEE6;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
        <tr>
          <td align="center" style="background-color:#3A3530; padding:26px 20px;">
            <span style="font-family:Georgia,'Times New Roman',serif; font-size:20px; color:#FBF6EF; letter-spacing:0.5px;">{{tenant_name}} Digest</span>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:32px 40px 4px 40px;">
            <p style="margin:0 0 6px 0; font-family:Georgia,'Times New Roman',serif; font-size:20px; color:#3A3530;">Hi {{first_name}},</p>
            <p style="margin:0 0 24px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#5C574D;">
              Here's what's been happening across {{tenant_name}} since our last update — a quick catch-up on the moments and milestones worth knowing about.
            </p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="border-top:1px solid #F3EEE6; font-size:1px; line-height:1px; padding-bottom:22px;">&nbsp;</td></tr>
            </table>
            <h2 style="margin:0 0 8px 0; font-family:Georgia,'Times New Roman',serif; font-size:19px; color:#C1622D; font-weight:normal;">New Faces, Warm Welcomes</h2>
            <p style="margin:0 0 6px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.6; color:#5C574D;">
              A wonderful group of new members joined us this month, bringing fresh skills and ideas to share with the community.
            </p>
            <p style="margin:0 0 24px 0;">
              <a href="#" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#C1622D; text-decoration:underline;">Read more &rarr;</a>
            </p>
            <h2 style="margin:0 0 8px 0; font-family:Georgia,'Times New Roman',serif; font-size:19px; color:#C1622D; font-weight:normal;">Skills Shared This Month</h2>
            <p style="margin:0 0 6px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.6; color:#5C574D;">
              From gardening tips to language lessons, members exchanged time and talents in ways big and small.
            </p>
            <p style="margin:0 0 24px 0;">
              <a href="#" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#C1622D; text-decoration:underline;">Read more &rarr;</a>
            </p>
            <h2 style="margin:0 0 8px 0; font-family:Georgia,'Times New Roman',serif; font-size:19px; color:#C1622D; font-weight:normal;">Upcoming on the Calendar</h2>
            <p style="margin:0 0 6px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.6; color:#5C574D;">
              Mark your calendar for a handful of gatherings and workshops coming up over the next few weeks.
            </p>
            <p style="margin:0 0 8px 0;">
              <a href="#" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; color:#C1622D; text-decoration:underline;">Read more &rarr;</a>
            </p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:28px 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FBF6EF; border-radius:10px;">
              <tr>
                <td align="center" class="stat-cell" width="33%" style="padding:22px 8px;">
                  <p style="margin:0; font-family:Georgia,'Times New Roman',serif; font-size:26px; color:#C1622D;">128</p>
                  <p style="margin:4px 0 0 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; color:#5C574D;">Hours Exchanged</p>
                </td>
                <td align="center" class="stat-cell" width="33%" style="padding:22px 8px;">
                  <p style="margin:0; font-family:Georgia,'Times New Roman',serif; font-size:26px; color:#C1622D;">34</p>
                  <p style="margin:4px 0 0 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; color:#5C574D;">New Members</p>
                </td>
                <td align="center" class="stat-cell" width="33%" style="padding:22px 8px;">
                  <p style="margin:0; font-family:Georgia,'Times New Roman',serif; font-size:26px; color:#C1622D;">9</p>
                  <p style="margin:4px 0 0 0; font-families:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; color:#5C574D;">Events Held</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" align="center" style="padding:4px 40px 40px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#C1622D" style="border-radius:8px;">
                  <a href="#" target="_blank" style="display:inline-block; padding:14px 32px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">Visit {{tenant_name}}</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:24px 40px 36px 40px; background-color:#FBF6EF;">
            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#5C574D;">
              You're receiving this digest because you're a member of {{tenant_name}}.<br>
              <a href="{{unsubscribe_url}}" style="color:#C1622D; text-decoration:underline;">Unsubscribe from these emails</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML,
    ],
    [
        'name' => 'Event Invite',
        'description' => 'A hero event invitation with a date, time, and location block plus an RSVP button.',
        'category' => 'starter',
        'content_format' => 'html',
        'thumbnail' => null,
        'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Event Invite</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  @media (max-width: 600px) {
    .email-container { width: 100% !important; }
    .stack-padding { padding-left: 20px !important; padding-right: 20px !important; }
    .hero-img { height: auto !important; }
    .detail-label { width:100px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#F3EEE6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">You're invited — join us at {{tenant_name}}.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3EEE6;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
        <tr>
          <td>
            <img src="https://via.placeholder.com/600x240" width="600" height="240" alt="" class="hero-img" style="display:block; width:100%; max-width:600px; height:auto; border:0;">
          </td>
        </tr>
        <tr>
          <td class="stack-padding" align="center" style="padding:36px 40px 6px 40px;">
            <p style="margin:0 0 6px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#E8A13D;">You're Invited</p>
            <h1 style="margin:0 0 12px 0; font-family:Georgia,'Times New Roman',serif; font-size:28px; line-height:1.3; color:#3A3530; font-weight:normal;">Community Gathering &amp; Skill Swap</h1>
            <p style="margin:0 0 4px 0; font-family:Georgia,'Times New Roman',serif; font-size:17px; color:#3A3530;">Hi {{first_name}},</p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 40px 8px 40px;">
            <p style="margin:0 0 20px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#5C574D;">
              We'd love for you to join us at {{tenant_name}} for an afternoon of connection, conversation, and skill sharing. Bring a friend, bring an open mind, and come ready to meet your neighbours.
            </p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 40px 28px 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FBF6EF; border-radius:10px;">
              <tr>
                <td style="padding:22px 24px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td class="detail-label" width="90" valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:bold; color:#C1622D; padding:6px 0;">Date</td>
                      <td valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; color:#3A3530; padding:6px 0;">[Month] [Day]</td>
                    </tr>
                    <tr>
                      <td class="detail-label" width="90" valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:bold; color:#C1622D; padding:6px 0;">Time</td>
                      <td valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; color:#3A3530; padding:6px 0;">2:00 PM &ndash; 4:30 PM (local time)</td>
                    </tr>
                    <tr>
                      <td class="detail-label" width="90" valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; font-weight:bold; color:#C1622D; padding:6px 0;">Location</td>
                      <td valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; color:#3A3530; padding:6px 0;">The Community Hall, details to follow by email</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" align="center" style="padding:0 40px 40px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#C1622D" style="border-radius:8px;">
                  <a href="#" target="_blank" style="display:inline-block; padding:14px 36px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">RSVP Now</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:24px 40px 36px 40px; background-color:#FBF6EF;">
            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#5C574D;">
              You're receiving this invite because you're a member of {{tenant_name}}.<br>
              <a href="{{unsubscribe_url}}" style="color:#C1622D; text-decoration:underline;">Unsubscribe from these emails</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML,
    ],
    [
        'name' => 'Welcome',
        'description' => 'A warm onboarding welcome with three numbered getting-started steps and a profile completion CTA.',
        'category' => 'starter',
        'content_format' => 'html',
        'thumbnail' => null,
        'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Welcome</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  @media (max-width: 600px) {
    .email-container { width: 100% !important; }
    .stack-padding { padding-left: 20px !important; padding-right: 20px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#F3EEE6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">Welcome to {{tenant_name}} — let's get you started.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3EEE6;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
        <tr>
          <td align="center" style="background-color:#E8A13D; padding:32px 20px;">
            <span style="font-family:Georgia,'Times New Roman',serif; font-size:24px; color:#3A3530; letter-spacing:0.5px;">Welcome to {{tenant_name}}</span>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:36px 40px 8px 40px;">
            <p style="margin:0 0 8px 0; font-family:Georgia,'Times New Roman',serif; font-size:20px; color:#3A3530;">Hi {{first_name}},</p>
            <p style="margin:0 0 22px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#5C574D;">
              We're so glad you're here. {{tenant_name}} is a community built on sharing time, skills, and support &mdash; and every member makes it a little richer. Here's how to get settled in.
            </p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td valign="top" width="52" style="padding:14px 0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="36" height="36">
                    <tr>
                      <td align="center" valign="middle" bgcolor="#C1622D" width="36" height="36" style="border-radius:18px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF;">1</td>
                    </tr>
                  </table>
                </td>
                <td valign="middle" style="padding:14px 0;">
                  <p style="margin:0 0 2px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#3A3530;">Complete your profile</p>
                  <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; color:#5C574D;">Add a photo and a short bio so neighbours know who they're meeting.</p>
                </td>
              </tr>
              <tr>
                <td valign="top" width="52" style="padding:14px 0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="36" height="36">
                    <tr>
                      <td align="center" valign="middle" bgcolor="#C1622D" width="36" height="36" style="border-radius:18px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF;">2</td>
                    </tr>
                  </table>
                </td>
                <td valign="middle" style="padding:14px 0;">
                  <p style="margin:0 0 2px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#3A3530;">Share a skill or two</p>
                  <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; color:#5C574D;">List something you're happy to offer &mdash; from baking to bike repairs.</p>
                </td>
              </tr>
              <tr>
                <td valign="top" width="52" style="padding:14px 0 24px 0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="36" height="36">
                    <tr>
                      <td align="center" valign="middle" bgcolor="#C1622D" width="36" height="36" style="border-radius:18px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF;">3</td>
                    </tr>
                  </table>
                </td>
                <td valign="middle" style="padding:14px 0 24px 0;">
                  <p style="margin:0 0 2px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#3A3530;">Say hello</p>
                  <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; color:#5C574D;">Browse the community feed and introduce yourself to fellow members.</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" align="center" style="padding:8px 40px 40px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#C1622D" style="border-radius:8px;">
                  <a href="#" target="_blank" style="display:inline-block; padding:14px 32px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">Complete My Profile</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:24px 40px 36px 40px; background-color:#FBF6EF;">
            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#5C574D;">
              You're receiving this email because you joined {{tenant_name}}.<br>
              <a href="{{unsubscribe_url}}" style="color:#C1622D; text-decoration:underline;">Unsubscribe from these emails</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML,
    ],
    [
        'name' => 'Re-engagement',
        'description' => 'An empathetic "we\'ve missed you" message with a what\'s-new box and a come-back call to action.',
        'category' => 'starter',
        'content_format' => 'html',
        'thumbnail' => null,
        'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Re-engagement</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  @media (max-width: 600px) {
    .email-container { width: 100% !important; }
    .stack-padding { padding-left: 20px !important; padding-right: 20px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#F3EEE6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">We've missed you at {{tenant_name}} &mdash; here's what's new.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3EEE6;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:12px; overflow:hidden;">
        <tr>
          <td align="center" style="background-color:#3A3530; padding:26px 20px;">
            <span style="font-family:Georgia,'Times New Roman',serif; font-size:20px; color:#FBF6EF; letter-spacing:0.5px;">{{tenant_name}}</span>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:36px 40px 8px 40px;">
            <h1 style="margin:0 0 14px 0; font-family:Georgia,'Times New Roman',serif; font-size:26px; line-height:1.3; color:#3A3530; font-weight:normal;">We've missed you, {{first_name}}</h1>
            <p style="margin:0 0 20px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#5C574D;">
              It's been a little while since we've seen you around {{tenant_name}}, and we wanted to check in. Life gets busy, we understand &mdash; but the community is always here, and there's a warm welcome waiting whenever you're ready.
            </p>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 40px 28px 40px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FBF6EF; border-radius:10px;">
              <tr>
                <td style="padding:22px 24px;">
                  <p style="margin:0 0 12px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:bold; color:#C1622D; text-transform:uppercase; letter-spacing:0.5px;">What's New Since You've Been Away</p>
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td valign="top" width="20" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; color:#C1622D; padding:4px 0;">&bull;</td>
                      <td valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.6; color:#3A3530; padding:4px 0;">New members and fresh skills have joined the community.</td>
                    </tr>
                    <tr>
                      <td valign="top" width="20" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; color:#C1622D; padding:4px 0;">&bull;</td>
                      <td valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.6; color:#3A3530; padding:4px 0;">A handful of upcoming events and gatherings are open for sign-up.</td>
                    </tr>
                    <tr>
                      <td valign="top" width="20" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; color:#C1622D; padding:4px 0;">&bull;</td>
                      <td valign="top" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; line-height:1.6; color:#3A3530; padding:4px 0;">Several requests are waiting for someone with your skills.</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" align="center" style="padding:0 40px 40px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#C1622D" style="border-radius:8px;">
                  <a href="#" target="_blank" style="display:inline-block; padding:14px 32px; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">Come Back and Say Hello</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:24px 40px 36px 40px; background-color:#FBF6EF;">
            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:1.6; color:#5C574D;">
              You're receiving this email because you're a member of {{tenant_name}}.<br>
              <a href="{{unsubscribe_url}}" style="color:#C1622D; text-decoration:underline;">Unsubscribe from these emails</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML,
    ],
    [
        'name' => 'Simple Letter',
        'description' => 'A clean, mostly-text letter format for a personal, elegant note with a warm sign-off.',
        'category' => 'starter',
        'content_format' => 'html',
        'thumbnail' => null,
        'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>A Letter from {{tenant_name}}</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
  @media (max-width: 600px) {
    .email-container { width: 100% !important; }
    .stack-padding { padding-left: 24px !important; padding-right: 24px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#FBF6EF; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">A short note from {{tenant_name}}.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FBF6EF;">
  <tr>
    <td align="center" style="padding:48px 16px;">
      <table role="presentation" class="email-container" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px; max-width:560px;">
        <tr>
          <td align="center" style="padding-bottom:28px;">
            <span style="font-family:Georgia,'Times New Roman',serif; font-size:16px; color:#C1622D; letter-spacing:1px; text-transform:uppercase;">{{tenant_name}}</span>
          </td>
        </tr>
        <tr>
          <td class="stack-padding" style="padding:0 24px;">
            <p style="margin:0 0 26px 0; font-family:Georgia,'Times New Roman',serif; font-size:21px; color:#3A3530;">Dear {{first_name}},</p>

            <p style="margin:0 0 20px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.75; color:#3A3530;">
              I wanted to take a moment to write to you directly, rather than send another busy update. Communities like ours are built one small act at a time &mdash; an hour offered, a favour returned, a conversation that turns into a friendship. You are part of that.
            </p>

            <p style="margin:0 0 20px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.75; color:#3A3530;">
              Since [Month] [Day], we've watched {{tenant_name}} grow in ways that remind us why this work matters: neighbours helping neighbours, skills passed along freely, and trust quietly building between people who might never have met otherwise.
            </p>

            <p style="margin:0 0 30px 0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.75; color:#3A3530;">
              Thank you for being part of it. If there's ever anything you need, or any way we can support you, please don't hesitate to reach out.
            </p>

            <p style="margin:0 0 4px 0; font-family:Georgia,'Times New Roman',serif; font-size:16px; color:#3A3530;">With warm regards,</p>
            <p style="margin:0 0 40px 0; font-family:Georgia,'Times New Roman',serif; font-size:16px; color:#3A3530; font-style:italic;">The {{tenant_name}} Team</p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="border-top:1px solid #E8DFD1; font-size:1px; line-height:1px;">&nbsp;</td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" class="stack-padding" style="padding:20px 24px 0 24px;">
            <p style="margin:0; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.6; color:#5C574D;">
              You're receiving this note because you're a member of {{tenant_name}}.<br>
              <a href="{{unsubscribe_url}}" style="color:#C1622D; text-decoration:underline;">Unsubscribe from these emails</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML,
    ],
];

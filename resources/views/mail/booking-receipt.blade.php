<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Inspection Request Received - At Ease Inspections</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #F8FAFC;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #1E293B;
      -webkit-font-smoothing: antialiased;
    }
    table {
      border-collapse: separate;
    }
    a {
      color: #EA580C;
      text-decoration: none;
    }
    @media only screen and (max-width: 600px) {
      .container {
        width: 100% !important;
        padding: 12px !important;
      }
      .content-card {
        padding: 24px 18px !important;
      }
      .detail-col {
        display: block !important;
        width: 100% !important;
        margin-bottom: 12px !important;
      }
    }
  </style>
</head>
<body style="margin: 0; padding: 24px 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1E293B;">

  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center">
        <!-- Container -->
        <table role="presentation" class="container" width="600" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%; margin: 0 auto; padding: 0 16px;">
          
          <!-- Header Logo / Brand -->
          <tr>
            <td align="center" style="padding: 20px 0 24px 0;">
              <table role="presentation" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center">
                    <span style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: #0F172A; text-transform: uppercase;">
                      AT <span style="color: #EA580C;">EASE</span> INSPECTIONS
                    </span>
                    <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; color: #64748B; margin-top: 4px;">
                      A Home Decision, Made Clearer
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Main Card -->
          <tr>
            <td>
              <table role="presentation" class="content-card" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                
                <!-- Brand Top Accent Bar -->
                <tr>
                  <td height="6" style="background: linear-gradient(90deg, #EA580C 0%, #FB923C 100%); line-height: 6px; font-size: 6px;">&nbsp;</td>
                </tr>

                <!-- Card Content -->
                <tr>
                  <td style="padding: 36px 36px 28px 36px;">
                    
                    <!-- Status Banner -->
                    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                      <tr>
                        <td>
                          <div style="display: inline-block; padding: 6px 14px; background-color: #FFF7ED; border: 1px solid #FFEDD5; border-radius: 9999px; font-size: 12px; font-weight: 700; color: #C2410C; letter-spacing: 0.5px; text-transform: uppercase;">
                            Request Received &bull; Ref: {{ $reference }}
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding-top: 16px;">
                          <h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #0F172A; letter-spacing: -0.5px; line-height: 1.25;">
                            You're all set, {{ $customerName }}!
                          </h1>
                          <p style="margin: 8px 0 0 0; font-size: 15px; color: #475569; line-height: 1.55;">
                            Thank you for booking with At Ease Inspections. We have received your property inspection request and registered it directly in our scheduling system.
                          </p>
                        </td>
                      </tr>
                    </table>

                    <!-- Inspection Summary Box -->
                    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; margin-bottom: 28px; overflow: hidden;">
                      <tr>
                        <td style="padding: 20px;">
                          <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                            
                            @if($propertyAddress)
                            <tr>
                              <td style="padding-bottom: 14px;">
                                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B; margin-bottom: 4px;">
                                  Property Address
                                </div>
                                <div style="font-size: 15px; font-weight: 600; color: #0F172A;">
                                  {{ $propertyAddress }}
                                </div>
                              </td>
                            </tr>
                            @endif

                            <tr>
                              <td style="padding-top: 6px; border-top: 1px solid #E2E8F0;">
                                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                  <tr>
                                    @if($appointmentDate)
                                    <td class="detail-col" width="50%" valign="top" style="padding-top: 12px;">
                                      <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B; margin-bottom: 4px;">
                                        Requested Appointment
                                      </div>
                                      <div style="font-size: 14px; font-weight: 600; color: #0F172A;">
                                        {{ $appointmentDate }}
                                      </div>
                                      @if($appointmentTime)
                                      <div style="font-size: 13px; font-weight: 500; color: #EA580C; margin-top: 2px;">
                                        at {{ $appointmentTime }} (Office Local Time)
                                      </div>
                                      @endif
                                    </td>
                                    @endif

                                    @if($formattedTotal)
                                    <td class="detail-col" width="50%" valign="top" style="padding-top: 12px;">
                                      <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B; margin-bottom: 4px;">
                                        Verified Quote Total
                                      </div>
                                      <div style="font-size: 18px; font-weight: 800; color: #0F172A;">
                                        {{ $formattedTotal }}
                                      </div>
                                      <div style="font-size: 12px; color: #64748B; margin-top: 2px;">
                                        All selected services included
                                      </div>
                                    </td>
                                    @endif
                                  </tr>
                                </table>
                              </td>
                            </tr>

                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- What Happens Next Section -->
                    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 28px;">
                      <tr>
                        <td>
                          <h2 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #0F172A; letter-spacing: -0.2px;">
                            What Happens Next?
                          </h2>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                            <!-- Step 1 -->
                            <tr>
                              <td width="32" valign="top" style="padding-bottom: 16px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background-color: #EA580C; color: #FFFFFF; font-size: 12px; font-weight: 700; text-align: center; line-height: 24px;">1</div>
                              </td>
                              <td valign="top" style="padding-bottom: 16px; padding-left: 8px;">
                                <div style="font-size: 14px; font-weight: 600; color: #0F172A;">Schedule & Inspector Confirmation</div>
                                <div style="font-size: 13px; color: #64748B; margin-top: 2px; line-height: 1.45;">
                                  Our office coordination team will review route optimization and lock in your designated licensed inspector.
                                </div>
                              </td>
                            </tr>
                            <!-- Step 2 -->
                            <tr>
                              <td width="32" valign="top" style="padding-bottom: 16px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background-color: #EA580C; color: #FFFFFF; font-size: 12px; font-weight: 700; text-align: center; line-height: 24px;">2</div>
                              </td>
                              <td valign="top" style="padding-bottom: 16px; padding-left: 8px;">
                                <div style="font-size: 14px; font-weight: 600; color: #0F172A;">Pre-Inspection Agreement</div>
                                <div style="font-size: 13px; color: #64748B; margin-top: 2px; line-height: 1.45;">
                                  You will receive an automated email from our scheduling system with a secure link to review and electronically sign your inspection agreement.
                                </div>
                              </td>
                            </tr>
                            <!-- Step 3 -->
                            <tr>
                              <td width="32" valign="top">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background-color: #EA580C; color: #FFFFFF; font-size: 12px; font-weight: 700; text-align: center; line-height: 24px;">3</div>
                              </td>
                              <td valign="top" style="padding-left: 8px;">
                                <div style="font-size: 14px; font-weight: 600; color: #0F172A;">Inspection & Same-Day Report</div>
                                <div style="font-size: 13px; color: #64748B; margin-top: 2px; line-height: 1.45;">
                                  Our inspector arrives on time, performs a thorough evaluation, and prepares your comprehensive digital report.
                                </div>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <!-- Assistance / Contact Box -->
                    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #FFF7ED; border-radius: 12px; border: 1px dashed #FDBA74;">
                      <tr>
                        <td style="padding: 16px 20px;">
                          <div style="font-size: 13px; font-weight: 700; color: #9A3412;">
                            Have questions or need to make adjustments?
                          </div>
                          <div style="font-size: 13px; color: #C2410C; margin-top: 4px; line-height: 1.5;">
                            Call or text us anytime at <a href="tel:7707656869" style="font-weight: 700; color: #C2410C; text-decoration: underline;">(770) 765-6869</a> or reply directly to this email.
                          </div>
                        </td>
                      </tr>
                    </table>

                  </td>
                </tr>

                <!-- Card Footer Note -->
                <tr>
                  <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 18px 36px; text-align: center;">
                    <div style="font-size: 12px; color: #64748B;">
                      Order Reference: <strong style="color: #0F172A;">{{ $reference }}</strong> &bull; Registered in ISN Scheduling System
                    </div>
                  </td>
                </tr>

              </table>
            </td>
          </tr>

          <!-- Email Footer -->
          <tr>
            <td align="center" style="padding: 24px 0 16px 0;">
              <p style="margin: 0; font-size: 12px; color: #94A3B8; line-height: 1.6;">
                &copy; {{ date('Y') }} At Ease Inspections. All rights reserved.<br>
                Metro Atlanta & Surrounding Areas &bull; Licensed & Insured
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>

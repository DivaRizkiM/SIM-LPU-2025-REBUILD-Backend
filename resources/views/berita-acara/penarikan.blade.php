@php
$terbilangTgl_kuasa = $tanggal_kuasa;

$bulanKuasa = $nama_bulan_kuasa;
$nama_bulan = $nama_bulan;
$total_biaya = 0;
$total_pendapatan = 0;
$total_deviasi = 0;
@endphp
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<style type="text/css">
         #outlook a { padding:0; }
         .ReadMsgBody { width:100%; }
         .ExternalClass { width:100%; }
         .ExternalClass * { line-height:100%; }
         body { margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%; }
         table, td { border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt; }
         img { border:0;height:auto;line-height:100%; outline:none;text-decoration:none;-ms-interpolation-mode:bicubic; }
         p { display:block;margin:13px 0; }
         .bordered {
            border: 1px solid black;
         }
      </style>
      <style type="text/css">
         @media only screen and (max-width:480px) {
         @-ms-viewport { width:320px; }
         @viewport { width:320px; }
         }
      </style>
      <link href="https://fonts.googleapis.com/css?family=Ubuntu:300,400,500,700" rel="stylesheet" type="text/css">
      <link href="https://fonts.googleapis.com/css?family=Cabin:400,700" rel="stylesheet" type="text/css">
      <link href="https://fonts.googleapis.com/css?family=arial" rel="stylesheet" type="text/css">
      <style type="text/css">
         @import url(https://fonts.googleapis.com/css?family=Ubuntu:300,400,500,700);
         @import url(https://fonts.googleapis.com/css?family=Cabin:400,700);
         @import url(https://fonts.googleapis.com/css?family=arial);
      </style>
      <!--<![endif]-->
      <style type="text/css">
         @media only screen and (min-width:480px) {
         .mj-column-per-100 { width:100% !important; max-width: 100%; }
         .mj-column-per-50 { width:50% !important; max-width: 50%; }
         }
      </style>
      <style type="text/css">
      </style>
      <style type="text/css">.hide_on_mobile { display: none !important;}
         @media only screen and (min-width: 480px) { .hide_on_mobile { display: block !important;} }
         .hide_section_on_mobile { display: none !important;}
         @media only screen and (min-width: 480px) { .hide_section_on_mobile { display: table !important;} }
         .hide_on_desktop { display: block !important;}
         @media only screen and (min-width: 480px) { .hide_on_desktop { display: none !important;} }
         .hide_section_on_desktop { display: table !important;}
         @media only screen and (min-width: 480px) { .hide_section_on_desktop { display: none !important;} }
         [owa] .mj-column-per-100 {
         width: 100%!important;
         }
         [owa] .mj-column-per-50 {
         width: 50%!important;
         }
         [owa] .mj-column-per-33 {
         width: 33.333333333333336%!important;
         }
         p {
         margin: 0px;
         }
         @media only print and (min-width:480px) {
         .mj-column-per-100 { width:100%!important; }
         .mj-column-per-40 { width:40%!important; }
         .mj-column-per-60 { width:60%!important; }
         .mj-column-per-50 { width: 50%!important; }
         mj-column-per-33 { width: 33.333333333333336%!important; }
         }
      </style>
   </head>
   <body style="background-color:#FFFFFF;">
      <div style="background-color:#FFFFFF;">
         <div style="margin: 15px;">
         </div>
         <div style="Margin:0px auto;max-width:100%;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
               <tbody>
                  <tr>
                     <td style="border:0px #080808 solid;direction:ltr;font-size:0px;padding:9px 0px 9px 0px;text-align:center;vertical-align:top;">
                        <div class="mj-column-per-100 outlook-group-fix" style="font-size:13px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                           <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%">
                              <tr>
                                 <td align="left" style="font-size:0px;padding:0px 15px 5px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center;"><span style="font-size: 12px; font-family: arial, sans-serif;"><strong>BERITA ACARA</strong></span></p>
                                    </div>
                                 </td>
                              </tr>
                              <tr>
                                 <td align="left" style="font-size:0px;padding:0px 15px 5px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center;"><span style="font-size: 12px; font-family: arial, sans-serif;"><strong>PENARIKAN DATA PENYELENGGARAAN LAYANAN POS UNIVERSAL {{ $user_identity }}</strong></span></p>
                                    </div>
                                 </td>
                              </tr>
                              <tr>
                                 <td align="left" style="font-size:0px;padding:0px 15px 5px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center;transform: uppercase;"><span style="font-size: 12px; font-family: arial, sans-serif; text-transform: uppercase;"><strong >BULAN {{ $nama_bulan }}TAHUN ANGGARAN {{ $tahun_anggaran }}</strong></span></p>
                                    </div>
                                 </td>
                              </tr>
                           </table>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
         <div style="Margin:0px auto;max-width:600px;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
               <tbody>
                  <tr>
                     <td style="border-left:0px #000000 solid;border-right:0px #000000 solid;border-top:3px #000000 solid;direction:ltr;font-size:0px;padding:9px 0px 9px 0px;text-align:center;vertical-align:top;">
                        <div class="mj-column-per-100 outlook-group-fix" style="font-size:13px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                           <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-top:0px #000000 solid;vertical-align:top;" width="100%">
                           <tr>
                           <tr>
                                 <td align="left" style="font-size:0px;padding:0px 15px 5px 15px;word-break:break-word;">
                                       <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                          <p style="text-align: center;"><span style="font-size: 12px; font-family: arial, sans-serif; text-transform: uppercase;"><b>Nomor : {{ $nomor_verifikasi }}</b></span></p>
                                       </div>
                                    </td>
                                 </tr>
                                 <td align="left" style="font-size:0px;padding:25px 15px 5px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: justify;"><span style="font-size: 13px;"> &nbsp; &nbsp; &nbsp; &nbsp; Pada hari ini,
                                        <!-- <b>{{ $terbilangTgl_kuasa }}</b>, -->
                                        <b>{{$tanggal_kuasa}},</b>
                                         telah dilakukan penarikan data Layanan Pos Universal Bulan {{ $nama_bulan }} Tahun {{ substr($tanggal, 0, 4) }}.
                                       <br> &nbsp; &nbsp;
                                       Dari hasil penarikan data Penyelenggaraan Layanan Pos Universal Bulan {{ $nama_bulan }} Tahun Anggaran {{ $tahun_anggaran }}, telah dilakukan penarikan data terhadap data-data sebagai berikut:</span></p>
                                    </div>
                                 </td>
                              </tr>
                           </table>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
         <div style="Margin:0px auto;max-width:600px;">
            <table align="center" border="1px" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;margin-top: 10px;">
               <tbody>
                  <tr>
                     <th class="bordered" align="left" style="font-size:0px;padding:5px 5px 5px 5px;word-break:break-word;">
                        <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:center;color:#000000;">
                           Regional
                        </div>
                     </th>
                     <th class="bordered" align="left" style="font-size:0px;padding:5px 5px 5px 5px;word-break:break-word;">
                        <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:center;color:#000000;">
                           Biaya
                        </div>
                     </th>
                     <th class="bordered" align="left" style="font-size:0px;padding:5px 5px 5px 5px;word-break:break-word;">
                        <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:center;color:#000000;">
                           Pendapatan
                        </div>
                     </th>
                     <th class="bordered" align="left" style="font-size:0px;padding:5px 5px 5px 5px;word-break:break-word;">
                        <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:center;color:#000000;">
                           Deviasi
                        </div>
                     </th>
                  </tr>
                  @foreach($biaya as $row)
                    <tr>
                        <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;word-break:break-word;text-align:justify;color: #000000;border:1px solid #000000;">
                            <b>{{ $row->nama_regional }}</b>
                        </td>
                        <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;word-break:break-word;text-align:justify;color: #000000;border:1px solid #000000;">
                            Rp. {{ number_format($row->total_biaya_regional, 0, ',', '.') }}
                        </td>
                        <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;word-break:break-word;text-align:justify;color: #000000;border:1px solid #000000;">
                            Rp. {{ number_format($row->pendapatan_regional, 0, ',', '.') }}
                        </td>
                        <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;word-break:break-word;text-align:justify;color: #000000;border:1px solid #000000;">
                            @php
                            $total_biaya += $row->total_biaya_regional;

                            $total_pendapatan += $row->pendapatan_regional;
                            $deviasi = $row->total_biaya_regional - $row->pendapatan_regional;
                            $total_deviasi += $deviasi;
                            @endphp
                            Rp. {{ number_format($deviasi, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach

                  <tr>
                     <td class="bordered" style="font-size:13px;padding:5px 5px 5px 5px;text-align:center;color: #000000;border:1px solid #000000;">
                        <b>Total</b>
                     </td>
                     <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;text-align:justify;color: #000000;border:1px solid #000000;">
                     Rp. {{ number_format($total_biaya, 0, ',', '.') }}

                     </td>
                     <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;text-align:justify;color: #000000;border:1px solid #000000;">
                     Rp. {{ number_format($total_pendapatan, 0, ',', '.') }}

                     </td>
                     <td class="bordered" style="font-size:12px;padding:5px 5px 5px 5px;text-align:justify;color: #000000;border:1px solid #000000;">
                     Rp. {{ number_format($total_deviasi, 0, ',', '.') }}
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
         <div style="Margin:0px auto;max-width:600px;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
               <tbody>
                  <tr>
                     <td style="border-left:0px #000000 solid;border-right:0px #000000 solid;border-top:0px #000000 solid;direction:ltr;font-size:0px;padding:9px 0px 9px 0px;text-align:center;vertical-align:top;">
                        <div class="mj-column-per-100 outlook-group-fix" style="font-size:13px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                           <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-top:0px #000000 solid;vertical-align:top;" width="100%">
                              <tr>
                                 <td align="left" style="font-size:0px;padding:25px 15px 5px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       &nbsp; &nbsp;
                                       Demikian Berita Acara ini dibuat untuk digunakan sebagai pedoman pelaksanaan verifikasi Penyelenggaraan Layanan Pos Universal Bulan {{ $nama_bulan }} Tahun Anggaran {{ substr($tanggal, 0, 4) }}</span></p>
                                    </div>
                                 </td>
                              </tr>
                           </table>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
         <div style="Margin:0px auto;max-width:600px;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
               <tbody>
                  <tr>
                     <td style="border-bottom:0px #000000 solid;border-left:0px #000000 solid;border-right:0px #000000 solid;direction:ltr;font-size:0px;padding:9px 0px 9px 0px;text-align:center;vertical-align:top;">
                        <div class="mj-column-per-50 outlook-group-fix" style="font-size:13px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                           <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%">
                              <tr>
                                 <td align="left" style="font-size:0px;padding:15px 15px 15px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">PIHAK KEDUA</span></p>
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">SVP Portofolio Management</span></p>
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">PT Pos Indonesia (Persero)</span></p>
                                    </div>
                                 </td>
                              </tr>
                              <tr>
                                 <td align="left" style="font-size:0px;padding:35px 15px 15px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center; margin-top:40px;"> </p>
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">{{ $nama_pihak_kedua }}</span></p>
                                    </div>
                                 </td>
                              </tr>
                           </table>
                        </div>
                        <div class="mj-column-per-50 outlook-group-fix" style="font-size:13px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                           <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%">
                              <tr>
                                 <td align="left" style="font-size:0px;padding:15px 15px 15px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">PIHAK PERTAMA</span></p>
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">Ketua Tim Kerja</span></p>
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">Digitalisasi Layanan Pos Universal</span></p>
                                    </div>
                                 </td>
                              </tr>
                              <tr>
                                 <td align="left" style="font-size:0px;padding:35px 15px 15px 15px;word-break:break-word;">
                                    <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1.5;text-align:left;color:#000000;">
                                       <p style="text-align: center; margin-top:40px;"> </p>
                                       <p style="text-align: center;"><span style="font-family: arial, sans-serif; font-size: 12px;">{{ $nama_pihak_pertama }}</span></p>
                                    </div>
                                 </td>
                              </tr>
                           </table>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
      </div>
   </body>
</html>



<?php
require_once __DIR__ . '/../vendor/autoload.php';

$isGold = $certificate['certificate_type'] === 'Gold';
$accent = $isGold ? '#b58a32' : '#89939e';
$accentDark = $isGold ? '#785a20' : '#58636f';
$accentMid = $isGold ? '#d9ac4b' : '#c7cdd3';
$accentLight = $isGold ? '#ead9a9' : '#d8dde2';
$medalFace = $isGold ? '#e5bd59' : '#cdd2d7';
$signaturePath = __DIR__ . '/../assets/img/authorized-signature.jpg';
$signatureBytes = file_get_contents($signaturePath);
if ($signatureBytes === false) {
    throw new RuntimeException('Could not load the authorized certificate signature.');
}
$signatureDataUri = 'data:image/jpeg;base64,' . base64_encode($signatureBytes);

$qrRenderer = new \BaconQrCode\Renderer\ImageRenderer(
    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(180, 4),
    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
);
$qrWriter = new \BaconQrCode\Writer($qrRenderer);
$qrSvg = $qrWriter->writeString($verificationUrl);
if (!preg_match('/<path\b[^>]*\bd="([^"]+)"/', $qrSvg, $qrMatch)) {
    throw new RuntimeException('Could not generate the certificate verification QR code.');
}
$qrPath = $qrMatch[1];
$nameLength = mb_strlen((string) $certificate['student_name']);
$examLength = mb_strlen((string) $certificate['exam_name']);
$nameFontSize = max(48, min(76, 1500 / max(1, $nameLength)));
$examFontSize = max(28, min(40, 760 / max(1, $examLength) * 1.72));
$nameFit = $nameLength > 24 ? ' textLength="1000" lengthAdjust="spacingAndGlyphs"' : '';
$examFit = $examLength > 34 ? ' textLength="760" lengthAdjust="spacingAndGlyphs"' : '';
$issueDate = date('d F Y', strtotime($certificate['issue_date']));
$category = e($certificate['certificate_type']);
$studentName = e($certificate['student_name']);
$examName = e($certificate['exam_name']);
$certificateId = e($certificate['certificate_id']);
$institutionName = e(APP_NAME);
$percentage = number_format((float) $certificate['percentage'], 1);
$revokedMark = $certificate['status'] === 'revoked'
    ? '<g transform="rotate(-16 700 500)" opacity=".16"><rect x="250" y="445" width="900" height="100" rx="8" fill="#ffffff" stroke="#a61b1b" stroke-width="8"/><text x="700" y="515" text-anchor="middle" font-family="DejaVu Sans" font-size="64" font-weight="bold" letter-spacing="12" fill="#a61b1b">REVOKED</text></g>'
    : '';

ob_start();
?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="1400" height="990" viewBox="0 0 1400 990" role="img" aria-label="Academic Excellence Award certificate for <?= $studentName ?>">
  <rect width="1400" height="990" fill="#fbf8ee"/>
  <rect x="16" y="16" width="1368" height="958" rx="4" fill="none" stroke="<?= e($accentDark) ?>" stroke-width="12"/>
  <rect x="33" y="33" width="1334" height="924" rx="3" fill="none" stroke="<?= e($accent) ?>" stroke-width="4"/>
  <rect x="48" y="48" width="1304" height="894" rx="2" fill="none" stroke="<?= e($accentLight) ?>" stroke-width="2"/>

  <g fill="none" stroke="<?= e($accent) ?>" stroke-linecap="round" stroke-linejoin="round">
    <path d="M56 272C83 211 115 189 169 170C197 160 219 137 226 102M62 222C98 216 127 194 140 161C150 136 151 110 142 84M92 195C88 158 103 130 130 114M147 165C184 171 209 191 228 219M118 131C93 121 79 101 82 75C108 75 127 90 132 115M170 112C174 78 196 58 226 60C231 89 214 111 184 121M95 218C71 206 57 187 59 163C83 160 103 171 117 193" stroke-width="4"/>
    <path d="M76 229C91 206 110 196 133 190M136 160C157 151 177 136 190 117M102 164C116 148 124 131 126 111M172 178C193 177 210 187 226 204M99 117C112 117 125 124 133 136M180 94C193 81 207 77 221 82" stroke-width="2.5"/>
    <circle cx="82" cy="75" r="5" fill="<?= e($accent) ?>"/><circle cx="226" cy="60" r="5" fill="<?= e($accent) ?>"/>
    <circle cx="59" cy="163" r="5" fill="<?= e($accent) ?>"/><circle cx="226" cy="219" r="5" fill="<?= e($accent) ?>"/>
  </g>
  <g fill="<?= e($accentLight) ?>" stroke="<?= e($accent) ?>" stroke-width="2">
    <ellipse cx="92" cy="202" rx="8" ry="19" transform="rotate(-45 92 202)"/>
    <ellipse cx="116" cy="184" rx="8" ry="19" transform="rotate(-33 116 184)"/>
    <ellipse cx="145" cy="157" rx="8" ry="19" transform="rotate(35 145 157)"/>
    <ellipse cx="163" cy="134" rx="8" ry="19" transform="rotate(37 163 134)"/>
    <ellipse cx="105" cy="151" rx="8" ry="18" transform="rotate(-34 105 151)"/>
    <ellipse cx="190" cy="182" rx="8" ry="18" transform="rotate(55 190 182)"/>
  </g>
  <g fill="none" stroke="<?= e($accent) ?>" stroke-width="3">
    <path d="M24 425C43 409 43 391 24 375M1376 425C1357 409 1357 391 1376 375M24 615C43 599 43 581 24 565M1376 615C1357 599 1357 581 1376 565"/>
    <path d="M34 480H58M1342 480H1366M34 510H58M1342 510H1366"/>
  </g>
  <g transform="translate(1400 0) scale(-1 1)">
    <path d="M56 272C83 211 115 189 169 170C197 160 219 137 226 102M62 222C98 216 127 194 140 161C150 136 151 110 142 84M92 195C88 158 103 130 130 114M147 165C184 171 209 191 228 219M118 131C93 121 79 101 82 75C108 75 127 90 132 115M170 112C174 78 196 58 226 60C231 89 214 111 184 121" fill="none" stroke="<?= e($accent) ?>" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    <g fill="<?= e($accentLight) ?>" stroke="<?= e($accent) ?>" stroke-width="2">
      <ellipse cx="92" cy="202" rx="8" ry="19" transform="rotate(-45 92 202)"/><ellipse cx="116" cy="184" rx="8" ry="19" transform="rotate(-33 116 184)"/>
      <ellipse cx="145" cy="157" rx="8" ry="19" transform="rotate(35 145 157)"/><ellipse cx="163" cy="134" rx="8" ry="19" transform="rotate(37 163 134)"/>
      <ellipse cx="105" cy="151" rx="8" ry="18" transform="rotate(-34 105 151)"/><ellipse cx="190" cy="182" rx="8" ry="18" transform="rotate(55 190 182)"/>
    </g>
  </g>
  <g transform="translate(0 990) scale(1 -1)">
    <path d="M56 272C83 211 115 189 169 170C197 160 219 137 226 102M62 222C98 216 127 194 140 161C150 136 151 110 142 84M92 195C88 158 103 130 130 114M147 165C184 171 209 191 228 219M118 131C93 121 79 101 82 75C108 75 127 90 132 115M170 112C174 78 196 58 226 60C231 89 214 111 184 121" fill="none" stroke="<?= e($accent) ?>" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    <g fill="<?= e($accentLight) ?>" stroke="<?= e($accent) ?>" stroke-width="2">
      <ellipse cx="92" cy="202" rx="8" ry="19" transform="rotate(-45 92 202)"/><ellipse cx="116" cy="184" rx="8" ry="19" transform="rotate(-33 116 184)"/>
      <ellipse cx="145" cy="157" rx="8" ry="19" transform="rotate(35 145 157)"/><ellipse cx="163" cy="134" rx="8" ry="19" transform="rotate(37 163 134)"/>
      <ellipse cx="105" cy="151" rx="8" ry="18" transform="rotate(-34 105 151)"/><ellipse cx="190" cy="182" rx="8" ry="18" transform="rotate(55 190 182)"/>
    </g>
  </g>
  <g transform="translate(1400 990) scale(-1 -1)">
    <path d="M56 272C83 211 115 189 169 170C197 160 219 137 226 102M62 222C98 216 127 194 140 161C150 136 151 110 142 84M92 195C88 158 103 130 130 114M147 165C184 171 209 191 228 219M118 131C93 121 79 101 82 75C108 75 127 90 132 115M170 112C174 78 196 58 226 60C231 89 214 111 184 121" fill="none" stroke="<?= e($accent) ?>" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    <g fill="<?= e($accentLight) ?>" stroke="<?= e($accent) ?>" stroke-width="2">
      <ellipse cx="92" cy="202" rx="8" ry="19" transform="rotate(-45 92 202)"/><ellipse cx="116" cy="184" rx="8" ry="19" transform="rotate(-33 116 184)"/>
      <ellipse cx="145" cy="157" rx="8" ry="19" transform="rotate(35 145 157)"/><ellipse cx="163" cy="134" rx="8" ry="19" transform="rotate(37 163 134)"/>
      <ellipse cx="105" cy="151" rx="8" ry="18" transform="rotate(-34 105 151)"/><ellipse cx="190" cy="182" rx="8" ry="18" transform="rotate(55 190 182)"/>
    </g>
  </g>

  <g transform="translate(0 -22)" fill="<?= e($accent) ?>" stroke="<?= e($accentDark) ?>" stroke-width="1">
    <path d="m572 79 7 15 17 2-12 11 3 17-15-8-15 8 3-17-12-11 17-2z"/>
    <path d="m632 65 8 17 19 3-14 13 3 19-16-9-17 9 3-19-14-13 19-3z"/>
    <path d="m700 56 10 21 23 3-17 16 4 23-20-11-20 11 4-23-17-16 23-3z"/>
    <path d="m768 65 8 17 19 3-14 13 3 19-16-9-17 9 3-19-14-13 19-3z"/>
    <path d="m828 79 7 15 17 2-12 11 3 17-15-8-15 8 3-17-12-11 17-2z"/>
  </g>

  <g aria-label="<?= $category ?> award medal">
    <path d="M104 239 92 427l60-36 43 51 3-203z" fill="<?= e($accentDark) ?>" stroke="<?= e($accentLight) ?>" stroke-width="3"/>
    <path d="M119 240 115 394l39-24 34 39-5-170z" fill="<?= e($accentMid) ?>"/>
    <path d="m135 260 7 111 17-11 20 24-5-123z" fill="<?= e($accentLight) ?>" opacity=".72"/>
    <path d="M200 239 178 421l54-35 42 39 15-186z" fill="<?= e($accentDark) ?>" stroke="<?= e($accentLight) ?>" stroke-width="3"/>
    <path d="M214 242 197 390l37-24 31 30 12-151z" fill="<?= e($accentMid) ?>"/>
    <path d="m232 261-12 107 16-11 19 19 8-119z" fill="<?= e($accentLight) ?>" opacity=".72"/>
    <path d="M156 267 154 371l17 20M237 267 224 365l17 17" fill="none" stroke="<?= e($accentDark) ?>" stroke-width="4" opacity=".55"/>
    <circle cx="206" cy="207" r="87" fill="<?= e($accentDark) ?>" opacity=".3"/>
    <circle cx="206" cy="201" r="78" fill="<?= e($accentMid) ?>" stroke="<?= e($accentDark) ?>" stroke-width="5"/>
    <circle cx="206" cy="201" r="66" fill="<?= e($medalFace) ?>" stroke="<?= e($accentLight) ?>" stroke-width="6"/>
    <path d="M161 179C176 148 218 138 245 161" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" opacity=".48"/>
    <circle cx="206" cy="201" r="56" fill="none" stroke="<?= e($accentDark) ?>" stroke-width="3"/>
    <circle cx="206" cy="201" r="49" fill="none" stroke="<?= e($accentLight) ?>" stroke-width="3" stroke-dasharray="2 6"/>
    <path d="M160 180C174 145 221 132 249 162" fill="none" stroke="#ffffff" stroke-width="5" opacity=".45"/>
    <path d="m206 161 12 25 28 4-20 20 5 28-25-13-25 13 5-28-20-20 28-4z" fill="<?= e($accentDark) ?>" stroke="<?= e($accentLight) ?>" stroke-width="4"/>
    <path d="m206 174 8 17 18 2-13 12 3 18-16-9-16 9 3-18-13-12 18-2z" fill="<?= e($accentMid) ?>" opacity=".9"/>
    <circle cx="206" cy="201" r="4" fill="<?= e($accentLight) ?>"/>
  </g>

  <text x="700" y="161" text-anchor="middle" font-family="DejaVu Serif, serif" font-size="59" font-weight="bold" letter-spacing="2.5" fill="#17243b">ACADEMIC EXCELLENCE</text>
  <text x="700" y="216" text-anchor="middle" font-family="DejaVu Serif, serif" font-size="29" font-weight="bold" letter-spacing="12" fill="<?= e($accentDark) ?>">AWARD</text>
  <path d="M480 239H920M520 247H880" stroke="<?= e($accent) ?>" stroke-width="2"/>
  <path d="m470 243 8-8 8 8-8 8zM914 243l8-8 8 8-8 8z" fill="<?= e($accent) ?>"/>

  <text x="700" y="326" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="19" font-weight="bold" letter-spacing="3.2" fill="#626b78">THIS CERTIFICATE IS PROUDLY PRESENTED TO</text>
  <text x="700" y="405" text-anchor="middle" font-family="DejaVu Serif, serif" font-size="<?= number_format($nameFontSize, 1, '.', '') ?>" font-weight="bold" fill="#17243b"<?= $nameFit ?>><?= $studentName ?></text>
  <path d="M430 423H970" stroke="<?= e($accentLight) ?>" stroke-width="2"/>
  <text x="700" y="464" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="18" fill="#596372">In recognition of outstanding academic achievement, dedication, and commitment to excellence.</text>
  <text x="700" y="521" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="16" letter-spacing="1.3" fill="#626b78">FOR SUCCESSFULLY COMPLETING</text>
  <text x="700" y="563" text-anchor="middle" font-family="DejaVu Serif, serif" font-size="<?= number_format($examFontSize, 1, '.', '') ?>" font-weight="bold" fill="#263650"<?= $examFit ?>><?= $examName ?></text>

  <path d="M500 580H900L876 613 900 646H500l24-33z" fill="<?= e($accentDark) ?>" opacity=".24"/>
  <path d="M500 575H900L878 611 900 647H500l22-36z" fill="<?= e($accentMid) ?>" stroke="<?= e($accentDark) ?>" stroke-width="3"/>
  <path d="M514 584H886L869 611 886 638H514l17-27z" fill="#fffdf7" stroke="<?= e($accentLight) ?>" stroke-width="2"/>
  <path d="M539 611H563M837 611H861" stroke="<?= e($accent) ?>" stroke-width="3"/>
  <path d="m581 598 4 9 10 1-7 7 2 10-9-5-9 5 2-10-7-7 10-1zM819 598l4 9 10 1-7 7 2 10-9-5-9 5 2-10-7-7 10-1z" fill="<?= e($accent) ?>"/>
  <text x="700" y="619" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="20" font-weight="bold" letter-spacing="2" fill="<?= e($accentDark) ?>"><?= strtoupper($category) ?> AWARD</text>
  <text x="700" y="675" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="16" font-weight="bold" letter-spacing="2.2" fill="#687281">EXAMINATION SCORE</text>
  <text x="700" y="710" text-anchor="middle" font-family="DejaVu Serif, serif" font-size="34" font-weight="bold" fill="<?= e($accentDark) ?>"><?= e($percentage) ?>%</text>

  <g transform="translate(196 216) scale(.72)" fill="<?= e($accentLight) ?>" stroke="<?= e($accent) ?>" stroke-width="2">
    <path d="M604 766C574 739 556 709 551 680C574 697 592 724 604 766z"/>
    <path d="M625 781C590 765 566 742 551 714C579 723 607 745 625 781z"/>
    <path d="M650 791C615 784 587 766 566 742C598 745 629 762 650 791z"/>
    <path d="M677 796C644 797 614 785 589 765C620 761 652 773 677 796z"/>
    <path d="M796 766C826 739 844 709 849 680C826 697 808 724 796 766z"/>
    <path d="M775 781C810 765 834 742 849 714C821 723 793 745 775 781z"/>
    <path d="M750 791C785 784 813 766 834 742C802 745 771 762 750 791z"/>
    <path d="M723 796C756 797 786 785 811 765C780 761 748 773 723 796z"/>
  </g>
  <g transform="translate(196 216) scale(.72)" fill="none">
    <path d="M700 788C657 787 620 771 590 740M700 788C743 787 780 771 810 740" stroke="<?= e($accentDark) ?>" stroke-width="3"/>
    <path d="m700 790 9 10-9 10-9-10z" fill="<?= e($accent) ?>"/>
  </g>

  <path d="M158 807H1242" stroke="<?= e($accentLight) ?>" stroke-width="3"/>
  <path d="m158 807 8-8 8 8-8 8zM1234 807l8-8 8 8-8 8z" fill="<?= e($accent) ?>"/>
  <text x="190" y="850" font-family="DejaVu Sans, sans-serif" font-size="19" font-weight="bold" letter-spacing=".5" fill="#4d5968">ISSUE DATE</text>
  <text x="190" y="881" font-family="DejaVu Serif, serif" font-size="24" font-weight="bold" fill="#263650"><?= e($issueDate) ?></text>
  <text x="190" y="918" font-family="DejaVu Sans Mono, monospace" font-size="17" font-weight="bold" fill="#344158">ID: <?= $certificateId ?></text>

  <image x="500" y="818" width="200" height="64" preserveAspectRatio="xMidYMid meet" xlink:href="<?= e($signatureDataUri) ?>"/>
  <path d="M500 888H700" stroke="#7d8794" stroke-width="2"/>
  <text x="600" y="912" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="16" font-weight="bold" fill="#394457">AUTHORIZED SIGNATURE</text>

  <text x="850" y="858" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="22" font-weight="bold" fill="#263650"><?= $institutionName ?></text>
  <text x="850" y="887" text-anchor="middle" font-family="DejaVu Serif, serif" font-size="18" fill="#596372">Academic Excellence Award</text>

  <rect x="1045" y="815" width="112" height="112" fill="#ffffff" stroke="<?= e($accentLight) ?>" stroke-width="3"/>
  <path d="<?= e($qrPath) ?>" transform="translate(1051 821) scale(3)" fill="#111111" fill-rule="evenodd"/>
  <text x="1101" y="946" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="12" font-weight="bold" letter-spacing=".8" fill="<?= e($accentDark) ?>">SCAN TO VERIFY</text>
  <?= $revokedMark ?>
</svg>
<?php
$certificateSvg = ob_get_clean();
$certificateDataUri = 'data:image/svg+xml;base64,' . base64_encode($certificateSvg);
?>
<img class="achievement-certificate certificate-<?= e(strtolower($certificate['certificate_type'])) ?>" src="<?= e($certificateDataUri) ?>" alt="Academic Excellence Award certificate for <?= $studentName ?>">
<style>
.achievement-certificate {
  display: block;
  width: 100%;
  max-width: 1200px;
  height: auto;
  aspect-ratio: 1400 / 990;
  margin: 0 auto;
  object-fit: contain;
}
@media print {
  .achievement-certificate {
    width: 297mm;
    max-width: none;
    height: 210mm;
    margin: 0;
  }
}
</style>

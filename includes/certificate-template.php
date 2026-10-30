<style>
@page { size: A4 landscape; margin: 0; }
.achievement-certificate {
  box-sizing: border-box;
  width: 100%;
  min-height: 190mm;
  padding: 12mm;
  background: #fff;
  color: #172033;
  font-family: "DejaVu Sans", Arial, sans-serif;
}
.certificate-frame {
  box-sizing: border-box;
  min-height: 166mm;
  border: 4px solid #7a8796;
  padding: 5mm;
}
.certificate-inner {
  box-sizing: border-box;
  min-height: 154mm;
  border: 1px solid #7a8796;
  padding: 10mm 14mm 7mm;
  text-align: center;
  position: relative;
}
.certificate-silver .certificate-frame { border-color: #7a8796; }
.certificate-silver .certificate-inner { border-color: #7a8796; }
.certificate-silver .certificate-logo, .certificate-silver .certificate-rule { border-color: #7a8796; color: #7a8796; }
.certificate-silver .certificate-award { border-color: #7a8796; background: #eef1f4; color: #596675; }
.certificate-gold .certificate-frame { border-color: #b98512; }
.certificate-gold .certificate-inner { border-color: #b98512; }
.certificate-gold .certificate-logo, .certificate-gold .certificate-rule { border-color: #b98512; color: #b98512; }
.certificate-gold .certificate-award { border-color: #b98512; background: #fbf3dc; color: #8b620d; }
.certificate-platinum .certificate-frame { border-color: #50677d; }
.certificate-platinum .certificate-inner { border-color: #50677d; }
.certificate-platinum .certificate-logo, .certificate-platinum .certificate-rule { border-color: #50677d; color: #50677d; }
.certificate-platinum .certificate-award { border-color: #50677d; background: #eaf0f4; color: #435a70; }
.certificate-brand { color: #28374a; font-size: 15px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
.certificate-logo {
  width: 16mm; height: 16mm; margin: 0 auto 3mm;
  border: 2px solid #7a8796; border-radius: 50%;
  color: #7a8796; font-size: 17px; font-weight: bold;
  line-height: 15mm; letter-spacing: 1px;
}
.certificate-heading { margin: 2mm 0; color: #1d2939; font-family: Georgia, serif; font-size: 27px; letter-spacing: 4px; }
.certificate-rule { width: 42mm; margin: 3mm auto 4mm; border-top: 2px solid #7a8796; }
.certificate-lead { color: #586579; font-size: 12px; }
.certificate-name { margin: 2mm 0; color: #18263a; font-family: Georgia, serif; font-size: 26px; font-weight: bold; }
.certificate-copy { margin: 2mm 0; color: #586579; font-size: 12px; }
.certificate-exam { margin: 1mm 0 2mm; color: #26364b; font-family: Georgia, serif; font-size: 20px; font-weight: bold; }
.certificate-award {
  display: inline-block; margin: 2mm 0; padding: 2mm 8mm;
  border: 1px solid #7a8796; background: #eef1f4;
  color: #596675; font-size: 12px; font-weight: bold;
  letter-spacing: 2px; text-transform: uppercase;
}
.certificate-stats { width: 72%; margin: 3mm auto; border-collapse: collapse; }
.certificate-stats td { width: 50%; padding: 2mm; border-top: 1px solid #d7dde5; color: #445267; font-size: 11px; }
.certificate-stats strong { color: #172033; font-size: 14px; }
.certificate-footer { width: 100%; margin-top: 6mm; border-collapse: collapse; }
.certificate-footer td { width: 33%; vertical-align: bottom; color: #506075; font-size: 9px; }
.certificate-signature { width: 43mm; margin: 0 auto 2mm; color: #243247; font-size: 10px; font-weight: bold; }
.certificate-signature-image { display: block; width: 34mm; height: 12mm; margin: 0 auto 1mm; object-fit: contain; }
.certificate-signature-label { display: block; border-top: 1px solid #718096; padding-top: 1mm; }
.certificate-id { font-family: "DejaVu Sans Mono", monospace; font-size: 8px; }
.certificate-verification { overflow-wrap: anywhere; font-size: 7px; }
.certificate-revoked { position: absolute; top: 45%; left: 15%; width: 70%; color: #a61b1b; border: 4px solid #a61b1b; font-size: 35px; font-weight: bold; letter-spacing: 8px; opacity: .16; transform: rotate(-15deg); }
</style>
<?php $typeClass = strtolower($certificate['certificate_type']); ?>
<section class="achievement-certificate certificate-<?= e($typeClass) ?>">
  <div class="certificate-frame">
    <div class="certificate-inner">
      <?php if ($certificate['status'] === 'revoked'): ?><div class="certificate-revoked">REVOKED</div><?php endif; ?>
      <div class="certificate-logo">OES</div>
      <div class="certificate-brand"><?= e(APP_NAME) ?></div>
      <h1 class="certificate-heading">CERTIFICATE OF ACHIEVEMENT</h1>
      <div class="certificate-rule"></div>
      <div class="certificate-lead">This certificate is proudly presented to</div>
      <div class="certificate-name"><?= e($certificate['student_name']) ?></div>
      <div class="certificate-copy">in recognition of successful achievement in</div>
      <div class="certificate-exam"><?= e($certificate['exam_name']) ?></div>
      <div class="certificate-award"><?= e($certificate['certificate_type']) ?> Achievement</div>
      <table class="certificate-stats">
        <tr>
          <td>RESULT<br><strong><?= number_format((float) $certificate['percentage'], 1) ?>%</strong></td>
          <td>GRADE<br><strong><?= e($certificate['grade']) ?></strong></td>
        </tr>
      </table>
      <?php
        $signaturePath = __DIR__ . '/../assets/img/authorized-signature.jpg';
        $signatureBytes = file_get_contents($signaturePath);
        if ($signatureBytes === false) {
            throw new RuntimeException('Could not load the authorized certificate signature.');
        }
        $signatureDataUri = 'data:image/jpeg;base64,' . base64_encode($signatureBytes);
      ?>
      <table class="certificate-footer">
        <tr>
          <td>COMPLETED<br><strong><?= e(date('d F Y', strtotime($certificate['submitted_at']))) ?></strong></td>
          <td><div class="certificate-signature"><img class="certificate-signature-image" src="<?= e($signatureDataUri) ?>" alt="Authorized signature"><span class="certificate-signature-label">Authorized Signature</span></div><?= e(APP_NAME) ?></td>
          <td>CERTIFICATE ID<br><strong class="certificate-id"><?= e($certificate['certificate_id']) ?></strong><br>
            <span class="certificate-verification"><?= e($verificationUrl) ?></span>
          </td>
        </tr>
      </table>
    </div>
  </div>
</section>

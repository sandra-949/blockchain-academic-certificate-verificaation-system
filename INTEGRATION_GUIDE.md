# PDF Certificate Download Feature - Integration Guide

## 1. Files to add to your certverify/ project

Copy these into your project like this:

```
certverify/
├── lib/
│   ├── fpdf.php          ← NEW
│   └── qrcode.php        ← NEW
└── actions/
    └── generate_certificate_pdf.php   ← NEW
```

## 2. IMPORTANT: Update the project path

Open `actions/generate_certificate_pdf.php` and find this line near the top:

```php
$projectPath = '/certverify'; // change if your folder name differs
```

Change `/certverify` to match your actual folder name on the server if it's different
(e.g. if your project lives at `htdocs/cavendish/`, set it to `/cavendish`).

## 3. Add the "Download PDF" button to certificates.php

Open `pages/certificates.php` and find this line inside the Actions column
(in the `<td>` that has the "Verify" link):

```php
<a href="verify.php?hash=<?php echo urlencode($cert['hashValue']); ?>"
   style="font-size:0.8rem;color:var(--primary-light);font-weight:600;margin-right:8px;">
    <i class="fas fa-search"></i> Verify
</a>
```

Add this right after it (still inside the same `<td>`):

```php
<a href="../actions/generate_certificate_pdf.php?id=<?php echo $cert['certificateID']; ?>"
   target="_blank"
   style="font-size:0.8rem;color:var(--success);font-weight:600;margin-right:8px;">
    <i class="fas fa-file-pdf"></i> PDF
</a>
```

## 4. (Optional) Add a download button right after issuing a certificate

Open `pages/issue_certificate.php` and find this block (inside the success card):

```php
<button onclick="copyHash('<?php echo $generatedHash; ?>')" class="btn-accent w-100 mb-2">
    <i class="fas fa-copy me-2"></i>Copy Hash
</button>
```

Add this right after it, using `$certID` (you'll need to also store the inserted ID —
see step 5 below):

```php
<a href="../actions/generate_certificate_pdf.php?id=<?php echo $certID; ?>"
   target="_blank" class="btn-primary-cv w-100 mb-2" style="display:block;text-align:center;">
    <i class="fas fa-file-pdf me-2"></i>Download Certificate PDF
</a>
```

## 5. Make sure $certID is available after issuing

In `issue_certificate.php`, the variable `$certID` is already set right after insert
(`$certID = $conn->insert_id;`). Just make sure it's included in the `compact()` call
or kept in scope so the PDF download link above can use it. If it's not already
visible at that point in your version, add this line right under
`$certData = compact(...)`:

```php
$certData['certificateID'] = $certID;
```

And in the link, use `$certData['certificateID']` instead of `$certID` if needed.

## How it works

- Clicking "PDF" calls `generate_certificate_pdf.php?id=123`
- The script checks the user is logged in (and, if an Institution user, that they own
  that certificate)
- It fetches the certificate from the database
- It builds the verification URL using the certificate's hash
  (e.g. `https://yoursite.com/certverify/public_verify.php?hash=abc123...`)
- It generates a QR code from that URL using a pure-PHP QR library (no external API calls)
- It draws a landscape A4 certificate using FPDF, with the QR code embedded bottom-right
- The browser receives a forced download (`Content-Disposition: attachment`)

## No installation needed

Both `fpdf.php` and `qrcode.php` are single self-contained PHP files with **zero
dependencies** — no Composer, no PECL extensions, nothing to install. Just drop them
in `lib/` and they work on any standard PHP 7+/8+ server, including shared hosting.

## Testing it

1. Place all files as shown above
2. Log in to CertVerify
3. Go to Certificates → click "PDF" next to any certificate
4. A PDF should download immediately showing the certificate with a scannable QR code

If you get a blank page or PHP error, check:
- `lib/fpdf.php` and `lib/qrcode.php` are both present
- Your PHP version is 7.0+ (check with `php -v` on your server)
- File permissions allow the script to read the lib files

{{-- أنماط شهادة مسابقة الطلاب، تُكتب لـ mPDF منفصلة عن محتواها (GenerateCompetitionCertificate) --}}
body { font-family: xbriyaz; color: #17202E; text-align: center; }
.background { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
.background img { width: 297mm; height: 210mm; }
.frame { padding: 45mm 40mm 0; }
.frame.plain { border: 6px double #2E6898; margin: 12mm; padding: 20px 40px; height: 160mm; }
.temporary { background: #F3F7FB; border: 1px dashed #2E6898; color: #1F4565; padding: 6px; font-size: 13pt; }
.org { color: #2E6898; font-size: 18pt; margin-top: 18px; }
h1 { color: #2E6898; font-size: 32pt; margin: 14px 0 8px; }
.line { width: 70px; border-top: 3px solid #2E6898; margin: 0 auto 20px; }
.label { color: #556170; font-size: 13pt; }
.value { font-size: 22pt; font-weight: bold; margin-bottom: 12px; }
.value.small { font-size: 16pt; }
.missing { color: #1F4565; font-size: 12pt; margin-top: 14px; }
.ref { color: #556170; font-size: 10pt; margin-top: 12px; direction: ltr; }

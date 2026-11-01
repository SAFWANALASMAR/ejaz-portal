{{-- أنماط طباعة النموذج المعبأ لـ mPDF، منفصلة عن محتواه (PrintFormEntry). كورقة وورد: أسود على أبيض وحدود رفيعة، والترويسة بلون الهوية --}}
body { font-family: xbriyaz; color: #111111; font-size: 12pt; line-height: 1.5; }
.letterhead { width: 100%; border-bottom: 2px solid #2E6898; padding-bottom: 6px; }
.letterhead td { vertical-align: middle; border: none; padding: 0; }
.letterhead-logo { text-align: left; width: 40%; }
.logo { height: 16mm; }
.org-name { color: #2E6898; font-size: 14pt; font-weight: bold; }
.org-contact { color: #444444; font-size: 9pt; }
.missing { color: #1F4565; }
.page-footer { width: 100%; border-top: 1px solid #999999; font-size: 9pt; color: #444444; padding-top: 4px; }
.page-footer td { border: none; padding: 0; }
.page-number { text-align: left; }
.sheet-title { text-align: center; font-size: 16pt; font-weight: bold; margin: 4px 0 6px; }
.sheet-meta { width: 100%; font-size: 9pt; color: #444444; margin-bottom: 10px; }
.sheet-meta td { border: none; padding: 0 0 0 8px; }
.sheet-heading { font-size: 13pt; font-weight: bold; background: #E9EEF4; border: 1px solid #555555; padding: 4px 8px; margin: 12px 0 0; }
.sheet-paragraph { margin: 8px 0; text-align: justify; }
.sheet-row { width: 100%; border-collapse: collapse; }
.sheet-row td { border: 1px solid #555555; padding: 6px 8px; vertical-align: top; }
.sheet-label { width: 25%; background: #F5F5F5; font-weight: bold; }
.pair .sheet-label { width: 15%; }
.pair .sheet-value { width: 35%; }
.sheet-table-label { font-weight: bold; margin: 10px 0 4px; }
.sheet-table { width: 100%; border-collapse: collapse; }
.sheet-table th, .sheet-table td { border: 1px solid #555555; padding: 5px 8px; text-align: right; }
.sheet-table th { background: #F5F5F5; }
.sheet-table .index { width: 8%; text-align: center; }
.signature { height: 55px; }
.sheet-footer-note { color: #444444; font-size: 9pt; margin-top: 16px; }

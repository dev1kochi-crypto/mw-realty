{{-- Invoice styles, shared by the PDF (dompdf) and the web invoice pages. --}}
<style>
    .inv { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; color: #1c2340; font-size: 12px; line-height: 1.5; }
    .inv table { border-collapse: collapse; }
    .inv-head { background: #264373; }
    .inv-head td { padding: 22px 28px; }
    .inv-logo { max-height: 46px; max-width: 200px; }
    .inv-brand-name { color: #fff; font-size: 20px; font-weight: bold; }
    .inv-title { color: #fff; font-size: 24px; font-weight: bold; letter-spacing: 3px; }
    .inv-number { color: #b9c7e3; font-size: 12px; margin-top: 2px; }
    .inv-meta td { padding: 24px 28px 8px; color: #4b5065; }
    .inv-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #838aa3; margin-bottom: 4px; }
    .inv-strong { font-weight: bold; color: #1c2340; }
    .inv-muted { color: #838aa3; font-size: 11px; }
    .inv-facts { margin: 16px 28px 0; width: calc(100% - 56px); background: #f5f7fc; }
    .inv-facts td { padding: 12px 14px; }
    .inv-status { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
    .inv-status--paid { background: #dcfce7; color: #15803d; }
    .inv-status--failed { background: #fee2e2; color: #b91c1c; }
    .inv-lines { margin: 22px 28px 0; width: calc(100% - 56px); }
    .inv-lines th { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #838aa3; padding: 8px 0; border-bottom: 2px solid #1c2340; }
    .inv-lines td { padding: 14px 0; border-bottom: 1px solid #e6e9f2; }
    .inv-totals { margin: 8px 28px 0; width: calc(100% - 56px); }
    .inv-totals td td { padding: 6px 0; }
    .inv-discount td { color: #0f9d58; }
    .inv-grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #1c2340; padding-top: 10px !important; }
    .inv-note { margin: 18px 28px 0; padding: 10px 14px; border-radius: 6px; font-size: 11px; }
    .inv-note--warn { background: #fef3c7; color: #92400e; }
    .inv-foot { margin: 34px 28px 24px; padding-top: 14px; border-top: 1px solid #e6e9f2; color: #838aa3; font-size: 10px; text-align: center; }
</style>

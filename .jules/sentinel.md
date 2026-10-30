# Sentinel Journal - Security Learnings

## 2025-02-17 - Error Information Leakage in PHPMailer Exception Handling
**Vulnerability:** Raw `$mail->ErrorInfo` was being exposed directly in JSON error responses to end users when SMTP email sending failed in `send-mail.php`.
**Learning:** Returning PHPMailer's raw `ErrorInfo` can leak sensitive infrastructure details, internal IP addresses, SMTP user credentials/hostnames, or internal path structures to clients.
**Prevention:** Catch exception and log internal errors server-side (or handle gracefully), while returning generic user-friendly error messages to the client response.

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/Env.php';

/**
 * MailService — Transaction notification e-mails via PHP's native mail().
 *
 * Addresses come from /home/sadakalo/App/.env — never hardcoded:
 *
 *   MAIL_TO          (alias: NOTIFY_EMAIL, EMAIL, ADMIN_EMAIL) — recipient
 *   MAIL_FROM        (alias: FROM_EMAIL)           — envelope/From address
 *   MAIL_FROM_NAME                                  — display name
 */
final class MailService
{
    private string $notifyEmail;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        Env::load();
        $this->notifyEmail = Env::get(['MAIL_TO', 'NOTIFY_EMAIL', 'EMAIL', 'ADMIN_EMAIL']);
        $this->fromEmail   = Env::get(['MAIL_FROM', 'FROM_EMAIL'], 'noreply@sadakalofashion.com');
        $this->fromName    = Env::get(['MAIL_FROM_NAME'], 'Sada Kalo Fashion');
    }

    /**
     * Send the transaction notification e-mail to the configured recipient.
     */
    public function sendTransactionNotice(
        string $shopName,
        string $trDate,
        string $description,
        float  $billAmount,
        float  $receivedAmount,
        string $enteredBy
    ): bool {
        if ($this->notifyEmail === '' || !filter_var($this->notifyEmail, FILTER_VALIDATE_EMAIL)) {
            return false; // Not configured — silently skip, never break the request.
        }

        $shop  = htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8');
        $memo  = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $by    = htmlspecialchars($enteredBy, ENT_QUOTES, 'UTF-8');
        $date  = date('d M Y', strtotime($trDate) ?: time());

        $subject = 'কাস্টমার ট্রানজাকশন — ' . $shopName;

        $body  = "<div style='font-family:Arial;padding:15px'>";
        $body .= "<h3>{$shop}</h3>";
        $body .= "<p><b>তারিখ:</b> {$date}</p>";
        $body .= "<p><b>মেমো:</b> {$memo}</p>";
        if ($billAmount > 0) {
            $body .= "<p style='color:green'><b>বিল:</b> ৳" . number_format($billAmount, 2) . '</p>';
        }
        if ($receivedAmount > 0) {
            $body .= "<p style='color:red'><b>জমা:</b> ৳" . number_format($receivedAmount, 2) . '</p>';
        }
        $body .= "<p><b>এন্ট্রি:</b> {$by}</p></div>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: =?UTF-8?B?' . base64_encode($this->fromName) . "?= <{$this->fromEmail}>\r\n";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        return @mail(
            $this->notifyEmail,
            $encodedSubject,
            $body,
            $headers,
            '-f' . $this->fromEmail
        );
    }
}

<?php
declare(strict_types=1);

class MailService {

    private string $fromEmail  = 'Support@sadakalofashion.com';
    private string $fromName   = 'Sada Kalo Fashion';
    private string $adminEmail = 'hisabkhata24@gmail.com';
    private string $websiteUrl = 'https://sadakalofashion.com/index.php';

    private const SMS_API_URL  = 'https://api.mimsms.com/api/SmsSending/SMS';
    private const SMS_USERNAME = 'sajpoint99@gmail.com';
    private const SMS_API_KEY  = 'HIWDMHZHVKH98ZGLI782THVLM';
    private const SMS_SENDER   = '8809617633941';
    private const SMS_TYPE     = 'T';

    // =============================================
    // SMS পাঠানো — "SadakaloOTP-547689" format
    // =============================================
    public function sendOtpSms(string $phone, string $otpCode): bool
    {
        $mobile = preg_replace('/\D/', '', $phone);
        if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
            $mobile = '88' . $mobile;
        } elseif (strlen($mobile) === 10) {
            $mobile = '880' . $mobile;
        }
        if (strlen($mobile) < 13) {
            $this->logError("Invalid phone: {$phone}");
            return false;
        }

        $message = "SadakaloOTP-{$otpCode}";

        $payload = json_encode([
            'UserName'        => self::SMS_USERNAME,
            'Apikey'          => self::SMS_API_KEY,
            'MobileNumber'    => $mobile,
            'SenderName'      => self::SMS_SENDER,
            'TransactionType' => self::SMS_TYPE,
            'CampaignId'      => 'null',
            'Message'         => $message,
        ]);

        $ch = curl_init(self::SMS_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: bearer',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) { $this->logError("cURL: {$curlError} | {$mobile}"); return false; }
        if ($httpCode !== 200) { $this->logError("HTTP {$httpCode} | {$response} | {$mobile}"); return false; }
        return true;
    }

    private function logError(string $msg): void
    {
        $dir = __DIR__ . '/../Logs';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/error_log.txt', '[' . date('Y-m-d H:i:s') . "] SMS: {$msg}\n", FILE_APPEND | LOCK_EX);
    }

    private function getEmailTemplate(string $title, string $body): string
    {
        return "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;border:2px solid #D4AF37;border-radius:10px;overflow:hidden;'>
            <div style='background:#1e293b;color:#fff;text-align:center;padding:20px;'>
                <h2 style='margin:0;color:#3b82f6;'>SADA KALO FASHION</h2>
            </div>
            <div style='padding:30px 20px;background:#f8fafc;text-align:center;'>
                <h3 style='color:#1e293b;'>{$title}</h3>
                <div style='font-size:16px;color:#334155;'>{$body}</div>
                <div style='margin-top:30px;'>
                    <a href='{$this->websiteUrl}' style='display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:12px 25px;border-radius:30px;font-weight:bold;'>লগইন পেজে যান</a>
                </div>
            </div>
            <div style='background:#e2e8f0;text-align:center;padding:15px;font-size:12px;'>© " . date('Y') . " Sada Kalo Fashion.</div>
        </div>";
    }

    private function sendMail(string $to, string $subject, string $html): bool
    {
        $h  = "MIME-Version: 1.0\r\n";
        $h .= "Content-type:text/html;charset=UTF-8\r\n";
        $h .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $h .= "Reply-To: {$this->adminEmail}\r\n";
        $h .= "Bcc: {$this->adminEmail}\r\n";
        return @mail($to, $subject, $html, $h);
    }

    public function sendOtpEmail(string $email, string $name, string $otp): bool
    {
        $body = "<p>হ্যালো <strong>{$name}</strong>, আপনার ওটিপি কোড:</p>
                 <div style='margin:20px 0'><span style='font-size:28px;font-weight:900;color:#10b981;letter-spacing:5px;border:2px dashed #10b981;padding:10px 20px;'>{$otp}</span></div>
                 <p style='color:#ef4444;'>কোডটি ১০ মিনিটের জন্য প্রযোজ্য।</p>";
        return $this->sendMail($email, 'Security OTP - Sada Kalo Fashion', $this->getEmailTemplate('OTP ভেরিফিকেশন', $body));
    }

    public function sendAdminOtpEmail(string $email, string $otp): bool
    {
        $body = "<p>আপনার অ্যাডমিন অ্যাকশন OTP কোড:</p>
                 <div style='margin:20px 0'><span style='font-size:28px;font-weight:900;color:#ef4444;letter-spacing:5px;border:2px dashed #ef4444;padding:10px 20px;'>{$otp}</span></div>
                 <p style='color:#ef4444;'>কোডটি ৫ মিনিটের জন্য প্রযোজ্য।</p>";
        return $this->sendMail($email, 'Admin Action OTP - Sada Kalo Fashion', $this->getEmailTemplate('Admin OTP', $body));
    }

    public function sendProfileUpdateConfirm(string $email, string $name): bool
    {
        $body = "<p>হ্যালো <strong>{$name}</strong>, আপনার প্রোফাইল তথ্য সফলভাবে পরিবর্তন করা হয়েছে।</p>";
        return $this->sendMail($email, 'Profile Updated - Sada Kalo Fashion', $this->getEmailTemplate('আপডেট সফল', $body));
    }
}
?>

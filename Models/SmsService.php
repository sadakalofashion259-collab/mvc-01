<?php
declare(strict_types=1);

class SmsService {
    private string $apiUrl = 'https://api.mimsms.com/api/SmsSending/SMS';
    private string $apiKey = 'HIWDMHZHVKH98ZGLI782THVLM';
    private string $username = 'sajpoint99@gmail.com';
    private string $senderName = '8809617633941';
    private string $smsType = 'T';

    public function sendOtp(string $phone, string $otp, string $name): bool {
        $mobile = preg_replace('/\D/', '', $phone);
        if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
            $mobile = '88' . $mobile;
        } elseif (strlen($mobile) === 10) {
            $mobile = '880' . $mobile;
        }

        if (strlen($mobile) < 13) {
            return false;
        }

        $message = "প্রিয় $name, আপনার ওটিপি কোডটি হলো: $otp. কোডটি ১০ মিনিটের জন্য প্রযোজ্য।";

        $postData = [
            'ApiKey'       => $this->apiKey,
            'Username'     => $this->username,
            'SenderName'   => $this->senderName,
            'SmsType'      => $this->smsType,
            'MobileNumber' => $mobile,
            'Message'      => $message
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return ($response !== false);
    }
}
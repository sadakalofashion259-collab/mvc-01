<?php
declare(strict_types=1);

class NotificationService {
    private string $appId = "cbd8af0c-e1f4-4229-a1f6-8452055837e6";
    private string $restApiKey = "os_v2_app_zpmk6dhb6rbctipwqrjakwbx4ysvx7ejj3qe2oml2kmbui5nw76o3gkelirukdzdukzfs27igxk5evprhas3qmhqbtpbwobgt6w2yti";

    public function sendPush(string $message): string {
        $content = ["en" => $message];
        $headings = ["en" => "📢 Sada Kalo Notice"];

        $fields = [
            'app_id' => $this->appId,
            'included_segments' => ['Subscribed Users'],
            'contents' => $content,
            'headings' => $headings,
            'url' => "https://sadakalohisabsystem.com"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->restApiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);
        return (string)$response;
    }
}
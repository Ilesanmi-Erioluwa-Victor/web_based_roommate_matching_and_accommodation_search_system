<?php

namespace RoomieMatch\Services;

use RoomieMatch\Config\Env;

class EmailService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.brevo.com/v3/smtp/email';

    public function __construct()
    {
        $this->apiKey = Env::get('BREVO_API_KEY');
    }

    public function sendEmailVerification(string $toEmail, string $toName, string $verificationLink): bool
    {
        return $this->send([
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => 'Verify your email - RoomieMatch',
            'htmlContent' => $this->templateEmailVerification($toName, $verificationLink),
        ]);
    }

    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink): bool
    {
        return $this->send([
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => 'Password Reset - RoomieMatch',
            'htmlContent' => $this->templatePasswordReset($toName, $resetLink),
        ]);
    }

    public function sendConnectionRequest(string $toEmail, string $toName, string $requesterName, string $acceptLink): bool
    {
        return $this->send([
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => 'New Roommate Connection Request - RoomieMatch',
            'htmlContent' => $this->templateConnectionRequest($toName, $requesterName, $acceptLink),
        ]);
    }

    public function sendConnectionAccepted(string $toEmail, string $toName, string $acceptorName, string $messageLink): bool
    {
        return $this->send([
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => 'Connection Accepted - RoomieMatch',
            'htmlContent' => $this->templateConnectionAccepted($toName, $acceptorName, $messageLink),
        ]);
    }

    public function sendMessageNotification(string $toEmail, string $toName, string $senderName, string $preview, string $threadLink): bool
    {
        return $this->send([
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => "New message from $senderName - RoomieMatch",
            'htmlContent' => $this->templateMessageNotification($toName, $senderName, $preview, $threadLink),
        ]);
    }

    public function sendListingExpiryReminder(string $toEmail, string $toName, string $listingTitle, string $renewLink): bool
    {
        return $this->send([
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => 'Your listing is about to expire - RoomieMatch',
            'htmlContent' => $this->templateExpiryReminder($toName, $listingTitle, $renewLink),
        ]);
    }

    private function send(array $payload): bool
    {
        if (empty($this->apiKey)) return false;

        $payload['sender'] = ['name' => 'RoomieMatch', 'email' => 'noreply@roomiematch.app'];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    private function templateEmailVerification(string $name, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2>Welcome to RoomieMatch!</h2>
<p>Hi $name,</p>
<p>Please verify your email address to start using RoomieMatch.</p>
<p><a href="$link" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">Verify Email</a></p>
<p>Or copy this link: <br><small>$link</small></p>
<p>If you did not create an account, ignore this email.</p>
</body></html>
HTML;
    }

    private function templatePasswordReset(string $name, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2>Password Reset</h2>
<p>Hi $name,</p>
<p>Click below to reset your password. This link expires in 1 hour.</p>
<p><a href="$link" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">Reset Password</a></p>
<p>If you did not request this, ignore this email.</p>
</body></html>
HTML;
    }

    private function templateConnectionRequest(string $name, string $requester, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2>New Connection Request</h2>
<p>Hi $name,</p>
<p><strong>$requester</strong> wants to connect with you as a potential roommate!</p>
<p><a href="$link" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">View Request</a></p>
</body></html>
HTML;
    }

    private function templateConnectionAccepted(string $name, string $acceptor, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2>Connection Accepted!</h2>
<p>Hi $name,</p>
<p><strong>$acceptor</strong> has accepted your connection request. You can now message each other!</p>
<p><a href="$link" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">Start Chatting</a></p>
</body></html>
HTML;
    }

    private function templateMessageNotification(string $name, string $sender, string $preview, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2>New Message</h2>
<p>Hi $name,</p>
<p><strong>$sender</strong> sent you a message:</p>
<blockquote style="padding:12px;background:#f5f5f5;border-left:4px solid #4F46E5;">$preview</blockquote>
<p><a href="$link" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">View Message</a></p>
</body></html>
HTML;
    }

    private function templateExpiryReminder(string $name, string $listingTitle, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2>Listing Expiring Soon</h2>
<p>Hi $name,</p>
<p>Your listing "<strong>$listingTitle</strong>" will expire soon. Renew it to keep it active.</p>
<p><a href="$link" style="display:inline-block;padding:12px 24px;background:#4F46E5;color:white;text-decoration:none;border-radius:6px;">Renew Listing</a></p>
</body></html>
HTML;
    }
}

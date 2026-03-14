<?php

namespace App\Services;

class EmailService
{
    private $smtp_host = "smtp.gmail.com";
    private $smtp_user = "acme.notifications@gmail.com";
    private $smtp_pass = "Gmail2024!";  // Hardcoded Gmail password
    private $from_email = "noreply@acme.com";

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($email, $password)
    {
        $subject = "Welcome to Acme Platform!";

        // Including plaintext password in email
        $body = "
            <h1>Welcome!</h1>
            <p>Your account has been created.</p>
            <p>Email: {$email}</p>
            <p>Password: {$password}</p>
            <p>Please don't share these credentials.</p>
        ";

        return $this->send($email, $subject, $body);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset($email, $resetToken)
    {
        $subject = "Password Reset";

        // Token in URL without HTTPS
        $resetLink = "http://acme.com/reset-password?token=" . $resetToken . "&email=" . $email;

        $body = "
            <p>Click here to reset your password:</p>
            <a href='{$resetLink}'>{$resetLink}</a>
            <p>This link is valid forever.</p>
        ";

        return $this->send($email, $subject, $body);
    }

    /**
     * Core send function
     */
    private function send($to, $subject, $body)
    {
        // Using mail() with unsanitized input - email header injection
        $headers = "From: " . $this->from_email . "\r\n";
        $headers .= "Reply-To: " . $to . "\r\n";  // Setting reply-to as recipient
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        // Vulnerable to header injection via $to
        $result = mail($to, $subject, $body, $headers);

        // Logging email content including passwords
        $this->logEmail($to, $subject, $body);

        return $result;
    }

    /**
     * Log sent emails
     */
    private function logEmail($to, $subject, $body)
    {
        // Writing sensitive data to world-readable log
        $logEntry = date('Y-m-d H:i:s') . " | To: " . $to . " | Subject: " . $subject . " | Body: " . $body . "\n";
        file_put_contents('/tmp/email_log.txt', $logEntry, FILE_APPEND);

        // Also logging to error_log
        error_log("Email sent to: " . $to . " - Body: " . $body);
    }

    /**
     * Send notification using system command
     */
    public function sendSmsNotification($phoneNumber, $message)
    {
        // Command injection vulnerability
        $command = "curl -X POST https://sms-api.acme.com/send -d 'phone=" . $phoneNumber . "&message=" . $message . "'";
        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Process email template from user input
     */
    public function sendCustomEmail($to, $templateName, $variables)
    {
        // Path traversal vulnerability
        $templatePath = __DIR__ . '/../../templates/' . $templateName . '.html';
        $template = file_get_contents($templatePath);

        // Replacing variables without sanitization
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $this->send($to, "Custom Notification", $template);
    }

    /**
     * Validate email format
     */
    public function isValidEmail($email)
    {
        // Overly simple regex, misses many cases
        return preg_match('/^.+@.+\..+$/', $email);
    }
}

<?php

/**
 * Send an application email
 * 
 * @param string $to Recipient email address
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $plainBody Plain text email body
 * @param string &$errorMsg Error message output
 * @return bool True if email sent successfully, false otherwise
 */
function send_app_email($to, $toName, $subject, $htmlBody, $plainBody, &$errorMsg = '') {
    $errorMsg = '';
    
    // Use PHP's built-in mail function
    // In production, consider using PHPMailer or SwiftMailer for better reliability
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: CPD-NIR Inventory System <noreply@cpd-nir.local>\r\n";
    $headers .= "Reply-To: noreply@cpd-nir.local\r\n";
    
    $to_address = $toName ? "$toName <$to>" : $to;
    
    if (mail($to, $subject, $htmlBody, $headers)) {
        return true;
    } else {
        $errorMsg = 'Failed to send email. Please check your mail server configuration.';
        return false;
    }
}

/**
 * Build account welcome email
 * 
 * @param string $username User's username
 * @param string $password User's password
 * @param string $role User's role
 * @param string $loginUrl Login page URL
 * @param string $logoUrl Logo image URL
 * @return array Array containing [htmlBody, plainBody]
 */
function build_account_welcome_email($username, $password, $role, $loginUrl, $logoUrl) {
    $roleDisplay = ucfirst($role);
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0284c7; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; }
        .footer { background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b; border-radius: 0 0 5px 5px; }
        .credentials { background-color: white; padding: 15px; border-left: 4px solid #0284c7; margin: 15px 0; }
        .credentials p { margin: 8px 0; }
        .label { font-weight: bold; color: #0284c7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Welcome to CPD-NIR Inventory System</h2>
        </div>
        <div class="content">
            <p>Good day,</p>
            <p>Your account has been successfully created in the CPD-NIR Inventory System.</p>
            
            <div class="credentials">
                <p><span class="label">Username:</span> {$username}</p>
                <p><span class="label">Password:</span> {$password}</p>
                <p><span class="label">Role:</span> {$roleDisplay}</p>
            </div>
            
            <p>Please use these credentials to log in at:</p>
            <p><a href="{$loginUrl}" style="color: #0284c7; text-decoration: none;">{$loginUrl}</a></p>
            
            <p><strong>Important:</strong> Please change your password immediately after your first login for security purposes.</p>
            
            <p>If you have any questions or need assistance, please contact the system administrator.</p>
            
            <p>Best regards,<br>CPD-NIR Inventory System</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = <<<TEXT
Welcome to CPD-NIR Inventory System

Good day,

Your account has been successfully created in the CPD-NIR Inventory System.

Credentials:
Username: {$username}
Password: {$password}
Role: {$roleDisplay}

Please use these credentials to log in at: {$loginUrl}

Important: Please change your password immediately after your first login for security purposes.

If you have any questions or need assistance, please contact the system administrator.

Best regards,
CPD-NIR Inventory System

---
This is an automated message. Please do not reply to this email.
TEXT;

    return [$htmlBody, $plainBody];
}

?>

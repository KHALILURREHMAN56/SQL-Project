<?php
// Display PHP mail configuration
echo "<h2>PHP Mail Configuration</h2>";
echo "<pre>";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "mail function exists: " . (function_exists('mail') ? 'Yes' : 'No') . "\n";
echo "</pre>";

// Test sending an email
$to = "test@example.com"; // Replace with your email for testing
$subject = "Test Email";
$message = "This is a test email from your PHP application.";
$headers = array(
    'MIME-Version: 1.0',
    'Content-type: text/plain; charset=UTF-8',
    'From: Anees Ice Cream Parlor <noreply@aneesicecream.com>',
    'Reply-To: noreply@aneesicecream.com',
    'X-Mailer: PHP/' . phpversion()
);
$headers = implode("\r\n", $headers);

echo "<h2>Attempting to send test email...</h2>";
$result = mail($to, $subject, $message, $headers);
echo "Mail send result: " . ($result ? "Success" : "Failed") . "<br>";

if (!$result) {
    $error = error_get_last();
    echo "Error details: " . ($error ? $error['message'] : 'Unknown error');
}
?> 
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>SMTP Connection Test</h2>";

$smtpServer = "localhost";
$smtpPort = 25;

echo "<pre>";
echo "Testing connection to SMTP server: $smtpServer:$smtpPort\n\n";

try {
    $socket = @fsockopen($smtpServer, $smtpPort, $errno, $errstr, 30);
    
    if (!$socket) {
        echo "Connection failed!\n";
        echo "Error $errno: $errstr\n";
    } else {
        echo "Connection successful!\n\n";
        
        // Read greeting
        $response = fgets($socket, 515);
        echo "Server greeting: " . $response . "\n";
        
        // Send HELO
        echo "\nSending HELO command...\n";
        fputs($socket, "HELO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response = fgets($socket, 515);
        echo "Response: " . $response . "\n";
        
        // Close connection
        fputs($socket, "QUIT\r\n");
        fclose($socket);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking PHP Mail Settings:\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "</pre>";
?> 
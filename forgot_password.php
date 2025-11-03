<?php
session_start();
require_once __DIR__ . '/config.php';

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

class PasswordReset {
    private $pdo;
    private $errors = [];
    private $success = false;
    private $maxAttempts = 3;
    private $lockoutTime = 900; // 15 minutes in seconds
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    public function handleRequest() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $this->processPasswordReset();
        }
        return ['errors' => $this->errors, 'success' => $this->success];
    }
    
    private function processPasswordReset() {
        $email = $this->sanitizeInput($_POST['email'] ?? '');
        
        if (!$this->isValidEmail($email)) {
            $this->errors[] = "Please enter a valid email address.";
            return;
        }
        
        if ($this->isRateLimited($email)) {
            $this->errors[] = "Too many attempts. Please try again later.";
            return;
        }
        
        try {
            if ($this->userExists($email)) {
                $token = $this->generateSecureToken();
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $this->storeResetToken($email, $token, $expiry);
                $this->sendResetEmail($email, $token);
            }
            
            // Always return success for security
            $this->success = true;
            
        } catch (Exception $e) {
            error_log("Password reset error for email {$email}: " . $e->getMessage());
            $this->success = true; // Still show success for security
        }
    }
    
    private function sanitizeInput($input) {
        return trim(strip_tags($input));
    }
    
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function isRateLimited($email) {
        // Delete old attempts (older than 15 minutes)
        $stmt = $this->pdo->prepare("DELETE FROM password_reset_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute();
        
        // Count recent attempts
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute([$email]);
        $attempts = $stmt->fetchColumn();
        
        if ($attempts >= $this->maxAttempts) {
            // Get time of first attempt to calculate remaining lockout time
            $stmt = $this->pdo->prepare("SELECT MIN(attempt_time) FROM password_reset_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$email]);
            $firstAttempt = $stmt->fetchColumn();
            
            if ($firstAttempt) {
                $lockoutEnd = strtotime($firstAttempt) + (15 * 60); // 15 minutes from first attempt
                $remainingTime = ceil(($lockoutEnd - time()) / 60); // Convert to minutes
                $this->errors[] = "Too many attempts. Please try again in {$remainingTime} minutes.";
            }
            return true;
        }
        
        // Log the attempt
        $stmt = $this->pdo->prepare("INSERT INTO password_reset_attempts (email, attempt_time) VALUES (?, NOW())");
        $stmt->execute([$email]);
        
        // Warn user about remaining attempts
        $remainingAttempts = $this->maxAttempts - $attempts - 1;
        if ($remainingAttempts < $this->maxAttempts && $remainingAttempts > 0) {
            $this->errors[] = "You have {$remainingAttempts} attempts remaining. Please wait 15 minutes if you run out of attempts.";
        }
        
        return false;
    }
    
    private function userExists($email) {
        $stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function storeResetToken($email, $token, $expiry) {
        $stmt = $this->pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $stmt->execute([$token, $expiry, $email]);
    }
    
    private function sendResetEmail($email, $token) {
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
        
        $stmt = $this->pdo->prepare("SELECT full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $fullName = $user['full_name'] ?? 'Valued Customer';
        
        $subject = "Password Reset Request - Anees Ice Cream Parlor";
        
        // HTML Email Template
        $htmlMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #30D5C8; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; padding: 10px 20px; background-color: #30D5C8; 
                         color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Dear " . htmlspecialchars($fullName) . ",</p>
                    <p>We received a request to reset your password. Click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($resetLink) . "' class='button'>Reset Password</a>
                    </p>
                    <p>This link will expire in 1 hour for security reasons.</p>
                    <p>If you didn't request this reset, please ignore this email or contact support if you have concerns.</p>
                    <p>Best regards,<br>Anees Ice Cream Parlor Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text version for email clients that don't support HTML
        $textMessage = "Dear " . $fullName . ",\n\n"
                    . "We received a request to reset your password. Click the link below to reset your password:\n\n"
                    . $resetLink . "\n\n"
                    . "This link will expire in 1 hour for security reasons.\n\n"
                    . "If you didn't request this reset, please ignore this email or contact support if you have concerns.\n\n"
                    . "Best regards,\nAnees Ice Cream Parlor Team";
        
        // Email headers
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: multipart/alternative; boundary="boundary123"',
            'From: Anees Ice Cream Parlor <noreply@localhost>',
            'Reply-To: support@localhost',
            'X-Mailer: PHP/' . phpversion()
        );
        
        $message = "--boundary123\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 7bit\r\n\r\n"
                . $textMessage . "\r\n\r\n"
                . "--boundary123\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 7bit\r\n\r\n"
                . $htmlMessage . "\r\n\r\n"
                . "--boundary123--";

        // Set error reporting to catch warnings
        $error_reporting = error_reporting(E_ALL);
        $display_errors = ini_get('display_errors');
        ini_set('display_errors', '0');
        
        try {
            // Try to send the email
            if(!@mail($email, $subject, $message, implode("\r\n", $headers))) {
                // Log the error for administrators
                error_log("Failed to send password reset email to: $email - Error: " . error_get_last()['message']);
                throw new Exception("Failed to send email");
            }
        } catch (Exception $e) {
            // Restore error reporting settings
            error_reporting($error_reporting);
            ini_set('display_errors', $display_errors);
            throw $e;
        }
        
        // Restore error reporting settings
        error_reporting($error_reporting);
        ini_set('display_errors', $display_errors);
    }
}

// Initialize and handle the password reset
$passwordReset = new PasswordReset();
$result = $passwordReset->handleRequest();
$errors = $result['errors'];
$success = $result['success'];

// Create password_reset_attempts table if it doesn't exist
try {
    $pdo = getDBConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        attempt_time DATETIME NOT NULL,
        INDEX (email, attempt_time)
    )");
} catch (PDOException $e) {
    error_log("Error creating password_reset_attempts table: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Anees Ice Cream Parlor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .auth-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .key-icon {
            width: 60px;
            height: 60px;
            background-color: #30D5C8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .key-icon i {
            color: white;
            font-size: 28px;
        }
        
        .auth-container h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 0.5rem;
        }
        
        .auth-container p {
            color: #666;
            margin-bottom: 1rem;
        }

        .success-message {
            color: #198754;
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            font-size: 0.95rem;
        }
        
        .form-control {
            border: 1px solid #ddd;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus {
            border-color: #30D5C8;
            box-shadow: 0 0 0 0.2rem rgba(48, 213, 200, 0.25);
        }
        
        .btn-primary {
            background: #30D5C8;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 10px;
            font-weight: 500;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #27b5aa;
            transform: translateY(-1px);
        }
        
        .back-to-login {
            color: #666;
            text-decoration: none;
            margin-top: 1rem;
            display: inline-block;
        }
        
        .back-to-login:hover {
            color: #30D5C8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row min-vh-100 justify-content-center align-items-center">
            <div class="col-12 text-center">
                <div class="auth-container mx-auto">
                    <div class="key-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h1>Forgot Password</h1>
                    
                    <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle me-2"></i>
                        Reset link has been sent to your email
                    </div>
                    <?php endif; ?>

                    <p>Enter your email to reset your password</p>

                    <?php if (!empty($errors) && !$success): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               required
                               placeholder="Enter your registered email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <div class="invalid-feedback">
                            Please enter a valid email address
                        </div>

                        <button type="submit" class="btn btn-primary mb-3">
                            Send Reset Link
                        </button>

                        <div>
                            <a href="login.php" class="back-to-login">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html> 
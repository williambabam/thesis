<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer manually
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

function sendOTPEmail($recipientEmail, $recipientName, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Server Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@gmail.com'; // REPLACE THIS
        $mail->Password   = 'xxxx xxxx xxxx xxxx';  // REPLACE WITH APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('your_email@gmail.com', 'ResortEase Partner Support');
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Partner Verification Code - ResortEase';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                <h2 style='color: #7c3aed;'>ResortEase Partner</h2>
                <p>Hi <strong>$recipientName</strong>,</p>
                <p>Thank you for applying as a Resort Partner. To complete your registration, please use this code:</p>
                <div style='background: #f5f3ff; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; color: #333; letter-spacing: 5px; border-radius: 8px;'>
                    $otp
                </div>
                <p style='margin-top: 20px; font-size: 12px; color: #666;'>If you did not request this, please ignore this email.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
<?php
/**
 * send-mail.php
 * Receives the Contact form (multipart/form-data or normal POST) and emails
 * the enquiry details directly — no third-party form service needed.
 *
 * SETUP (one-time):
 * 1. Upload this file + the "PHPMailer" folder to your website's server,
 *    in the same folder as index.html (or update the require paths below).
 * 2. Fill in the SMTP settings below (see instructions at the bottom of this file).
 * 3. In index.html, the form's fetch() already points to "send-mail.php".
 */

// ---------- CONFIG: EDIT THESE ----------
$SMTP_HOST     = 'smtp.gmail.com';           // Gmail SMTP server
$SMTP_USERNAME = 'Kunalanjna9910@gmail.com'; // The Gmail account that will SEND the mail
$SMTP_PASSWORD = 'iybdbwnvfrdjulof';         // Gmail "App Password" (NOT your normal password)
$SMTP_PORT     = 587;
$TO_EMAIL      = 'Kunalanjna9910@gmail.com'; // Where enquiries should be delivered
$TO_NAME       = 'Nakayoshi India Engineering Team';
// -----------------------------------------

header('Content-Type: application/json');

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ---- Basic validation ----
$required = ['name', 'company', 'email'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Sanitize inputs for safe email display
function clean($v) {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$name        = clean($_POST['name']);
$company     = clean($_POST['company']);
$email       = clean($_POST['email']);
$phone       = clean($_POST['phone'] ?? '');
$projectType = clean($_POST['projectType'] ?? '');
$details     = clean($_POST['details'] ?? '');

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USERNAME;
    $mail->Password   = $SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $SMTP_PORT;

    // Recipients
    $mail->setFrom($SMTP_USERNAME, 'Nakayoshi India Website');
    $mail->addAddress($TO_EMAIL, $TO_NAME);
    $mail->addReplyTo($email, $name); // so the team can hit "reply" and reach the enquirer

    // Content
    $mail->isHTML(true);
    $mail->Subject = "New Enquiry from Website - $name ($company)";
    $mail->Body    = "
        <h2>New Consultation Request</h2>
        <p><b>Name:</b> $name</p>
        <p><b>Company:</b> $company</p>
        <p><b>Email:</b> $email</p>
        <p><b>Phone:</b> " . ($phone ?: '—') . "</p>
        <p><b>Project Type:</b> " . ($projectType ?: '—') . "</p>
        <p><b>Details:</b><br>" . nl2br($details ?: '—') . "</p>
    ";
    $mail->AltBody = "New Enquiry\nName: $name\nCompany: $company\nEmail: $email\nPhone: $phone\nProject Type: $projectType\nDetails: $details";

    $mail->send();
    echo json_encode(['success' => true, 'message' => "Thank you — we'll be in touch!"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Mail could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
}
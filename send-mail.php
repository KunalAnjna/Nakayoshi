<?php
/**
 * send-mail.php  (COMBINED)
 * Single endpoint that handles BOTH:
 *   1) Contact / "Request Consultation" form  -> fields: name, company, email, phone, projectType, details
 *   2) Careers "Apply Here" form               -> fields: name, age, location, email, contact,
 *                                                   qualification, experience, position + resume_attachment file
 *
 * How it decides which form was submitted:
 *   - If a file was uploaded in "resume_attachment"  -> treated as a JOB APPLICATION
 *   - Else if "company" field is present              -> treated as a CONTACT ENQUIRY
 *   - Otherwise                                        -> error (unrecognized form)
 *
 * SETUP (one-time):
 * 1. Upload this file + the "PHPMailer" folder to your website's server,
 *    inside the "backend/" folder (or update the require paths below).
 * 2. Point BOTH forms' action to this same file:
 *      - Contact form   -> action="backend/send-mail.php"
 *      - Careers form   -> action="backend/send-mail.php"
 * 3. Fill in the SMTP settings below (Gmail "App Password", not your normal password).
 */

// ---------- CONFIG: EDIT THESE ----------
$SMTP_HOST     = 'smtp.gmail.com';           // Gmail SMTP server
$SMTP_USERNAME = 'Kunalanjna9910@gmail.com'; // The Gmail account that will SEND the mail
$SMTP_PASSWORD = 'iybdbwnvfrdjulof';         // Gmail "App Password" (NOT your normal password)
$SMTP_PORT     = 587;
$TO_EMAIL      = 'Kunalanjna9910@gmail.com'; // Where mail should be delivered
$TO_NAME       = 'Nakayoshi India / Cosmo Instruments Team';
// -----------------------------------------

header('Content-Type: application/json');

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function fail($message, $code = 400) {
    http_response_code($code);
    // "ok" is checked by the Careers form JS, "success" by the Contact form JS
    echo json_encode(['ok' => false, 'success' => false, 'error' => $message, 'message' => $message]);
    exit;
}

function clean($v) {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$hasResume = isset($_FILES['resume_attachment']) && $_FILES['resume_attachment']['error'] !== UPLOAD_ERR_NO_FILE;

$mail = new PHPMailer(true);

try {
    // Server settings (common to both forms)
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USERNAME;
    $mail->Password   = $SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $SMTP_PORT;
    $mail->addAddress($TO_EMAIL, $TO_NAME);
    $mail->isHTML(true);

    if ($hasResume) {
        // ============ JOB APPLICATION (Careers form) ============

        $required = ['name', 'age', 'location', 'email', 'contact', 'qualification', 'experience', 'position'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                fail("Missing field: $field");
            }
        }

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            fail('Invalid email address');
        }

        if ($_FILES['resume_attachment']['error'] !== UPLOAD_ERR_OK) {
            fail('Resume file failed to upload');
        }

        $resume       = $_FILES['resume_attachment'];
        $allowedExts  = ['pdf', 'doc', 'docx'];
        $maxSizeBytes = 5 * 1024 * 1024; // 5MB

        $ext = strtolower(pathinfo($resume['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            fail('Resume must be a PDF or Word file');
        }
        if ($resume['size'] > $maxSizeBytes) {
            fail('Resume file must be under 5MB');
        }

        $name          = clean($_POST['name']);
        $age           = clean($_POST['age']);
        $location      = clean($_POST['location']);
        $email         = clean($_POST['email']);
        $contact       = clean($_POST['contact']);
        $qualification = clean($_POST['qualification']);
        $experience    = clean($_POST['experience']);
        $position      = clean($_POST['position']);

        $mail->setFrom($SMTP_USERNAME, 'Cosmo Instruments Careers');
        $mail->addReplyTo($email, $name);
        $mail->addAttachment($resume['tmp_name'], $resume['name']);

        $mail->Subject = "New Job Application - $name ($position)";
        $mail->Body    = "
            <h2>New Job Application</h2>
            <p><b>Name:</b> $name</p>
            <p><b>Age:</b> $age</p>
            <p><b>Location:</b> $location</p>
            <p><b>Email:</b> $email</p>
            <p><b>Contact No:</b> $contact</p>
            <p><b>Educational Qualification:</b> $qualification</p>
            <p><b>Work Experience:</b> $experience</p>
            <p><b>Position Applying For:</b> $position</p>
            <p><b>Resume:</b> attached ({$resume['name']})</p>
        ";
        $mail->AltBody = "New Job Application\nName: $name\nAge: $age\nLocation: $location\nEmail: $email\nContact: $contact\nQualification: $qualification\nExperience: $experience\nPosition: $position\nResume attached: {$resume['name']}";

        $mail->send();
        echo json_encode(['ok' => true, 'success' => true, 'message' => "Application submitted — we'll be in touch!"]);

    } elseif (isset($_POST['company'])) {
        // ============ CONTACT ENQUIRY (Request Consultation form) ============

        $required = ['name', 'company', 'email'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                fail("Missing field: $field");
            }
        }

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            fail('Invalid email address');
        }

        $name        = clean($_POST['name']);
        $company     = clean($_POST['company']);
        $email       = clean($_POST['email']);
        $phone       = clean($_POST['phone'] ?? '');
        $projectType = clean($_POST['projectType'] ?? '');
        $details     = clean($_POST['details'] ?? '');

        $mail->setFrom($SMTP_USERNAME, 'Nakayoshi India Website');
        $mail->addReplyTo($email, $name);

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
        echo json_encode(['ok' => true, 'success' => true, 'message' => "Thank you — we'll be in touch!"]);

    } else {
        fail('Unrecognized form submission');
    }

} catch (Exception $e) {
    fail("Mail could not be sent. Mailer Error: {$mail->ErrorInfo}", 500);
}
<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 405, 'message' => 'Method Not Allowed']);
    exit;
}

global $DB;

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'Invalid JSON payload']);
    exit;
}

$form_id = isset($data['form_id']) ? intval($data['form_id']) : 0;
$answers = isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : [];

if ($form_id <= 0 || empty($answers)) {
    echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'form_id and answers are required']);
    exit;
}

try {
    if (isset($DB)) {
        // ตรวจสอบฟอร์ม
        $stmtCheck = $DB->prepare("SELECT FormID FROM survey_form WHERE FormID = :fid AND IsActive = 'Y'");
        $stmtCheck->execute([':fid' => $form_id]);
        if (!$stmtCheck->fetch()) {
             echo json_encode(['status' => 'error', 'code' => 404, 'message' => 'Form not found or inactive']);
             exit;
        }

        $DB->beginTransaction();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 1. Insert Submission
        $sqlSub = "INSERT INTO survey_submission (FormID, SubmitIP, SubmitAgent) VALUES (:fid, :ip, :agent)";
        $stmtSub = $DB->prepare($sqlSub);
        $stmtSub->execute([':fid' => $form_id, ':ip' => $ip, ':agent' => $agent]);
        $submission_id = $DB->lastInsertId();

        // 2. Loop insert answers
        $sqlAns = "INSERT INTO survey_answer (SubmissionID, QuestionID, Answer) VALUES (:sid, :qid, :ans)";
        $stmtAns = $DB->prepare($sqlAns);

        $sqlOpt = "INSERT INTO survey_answer_option (AnswerID, QuestionOptionID, QuestionOptionText) VALUES (:aid, :optid, :opttext)";
        $stmtOpt = $DB->prepare($sqlOpt);

        foreach ($answers as $ans) {
            $qid = isset($ans['question_id']) ? intval($ans['question_id']) : 0;
            if ($qid <= 0) continue;

            $text_answer = isset($ans['answer_text']) ? trim($ans['answer_text']) : '';
            $other_text = isset($ans['other_text']) ? trim($ans['other_text']) : '';
            $option_ids = isset($ans['option_ids']) && is_array($ans['option_ids']) ? $ans['option_ids'] : [];

            // Insert into survey_answer
            $stmtAns->execute([
                ':sid' => $submission_id,
                ':qid' => $qid,
                ':ans' => $text_answer
            ]);
            $answer_id = $DB->lastInsertId();

            // Insert into survey_answer_option (if any options selected)
            if (!empty($option_ids)) {
                foreach ($option_ids as $opt_id) {
                    $opt_id = intval($opt_id);
                    $stmtOpt->execute([
                        ':aid' => $answer_id,
                        ':optid' => $opt_id,
                        ':opttext' => $other_text
                    ]);
                }
            }
        }

        $DB->commit();

        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'message' => 'Survey submitted successfully',
            'data' => [
                'submission_id' => $submission_id
            ]
        ]);
    }
} catch (Exception $e) {
    if (isset($DB) && $DB->inTransaction()) {
        $DB->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}

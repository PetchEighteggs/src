<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

if ($form_id <= 0) {
    echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'form_id is required']);
    exit;
}

try {
    if (isset($DB)) {
        // ดึงข้อมูลฟอร์ม
        $stmtForm = $DB->prepare("SELECT FormID, FormName FROM survey_form WHERE FormID = :fid");
        $stmtForm->execute([':fid' => $form_id]);
        $form = $stmtForm->fetch(PDO::FETCH_ASSOC);

        if (!$form) {
            echo json_encode(['status' => 'error', 'code' => 404, 'message' => 'Form not found']);
            exit;
        }

        // ดึง Submissions ทั้งหมดของ Form นี้
        $stmtSub = $DB->prepare("SELECT * FROM survey_submission WHERE FormID = :fid ORDER BY CreateDate DESC LIMIT :limit");
        $stmtSub->bindValue(':fid', $form_id, PDO::PARAM_INT);
        $stmtSub->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtSub->execute();
        $submissions = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

        $response_data = [];

        if (!empty($submissions)) {
            // ดึงคำถามทั้งหมดของฟอร์มนี้เพื่อใช้เป็น Header อ้างอิง (รองรับ Matrix Parent)
            $stmtQ = $DB->prepare("
                SELECT q1.QuestionID, q1.Title, q1.Description, q2.Title as ParentTitle 
                FROM survey_question q1
                LEFT JOIN survey_question q2 ON q1.ParentQuestionID = q2.QuestionID
                WHERE q1.FormID = :fid
            ");
            $stmtQ->execute([':fid' => $form_id]);
            $questions = [];
            while ($q = $stmtQ->fetch(PDO::FETCH_ASSOC)) {
                $questions[$q['QuestionID']] = [
                    'title' => $q['Title'],
                    'description' => $q['Description'],
                    'parent_title' => $q['ParentTitle']
                ];
            }

            // เตรียม Query สำหรับดึงคำตอบของแต่ละ Submission
            $sqlAns = "
                SELECT 
                    a.AnswerID, a.QuestionID, a.Answer,
                    o.QuestionOptionID, qo.Title as OptionTitle, o.QuestionOptionText
                FROM survey_answer a
                LEFT JOIN survey_answer_option o ON a.AnswerID = o.AnswerID
                LEFT JOIN survey_question_option qo ON o.QuestionOptionID = qo.QuestionOptionID
                WHERE a.SubmissionID = :sid
            ";
            $stmtAns = $DB->prepare($sqlAns);

            foreach ($submissions as $sub) {
                $sid = $sub['SubmissionID'];
                $stmtAns->execute([':sid' => $sid]);
                $answers_raw = $stmtAns->fetchAll(PDO::FETCH_ASSOC);

                // จัดกลุ่มคำตอบตาม QuestionID
                $grouped_answers = [];
                foreach ($answers_raw as $row) {
                    $qid = $row['QuestionID'];
                    if (!isset($grouped_answers[$qid])) {
                        $qData = $questions[$qid] ?? null;
                        $qTitle = $qData ? $qData['title'] : 'Unknown';
                        
                        // ถ้านำคำถามเต็มมาแสดง (นำ Description มาต่อท้าย)
                        if ($qData && !empty($qData['description'])) {
                            $qTitle .= ' (' . $qData['description'] . ')';
                        }
                        
                        $fullTitle = ($qData && $qData['parent_title']) ? $qTitle : $qTitle;

                        $grouped_answers[$qid] = [
                            'question_id' => $qid,
                            'question_title' => $fullTitle,
                            'answer_text' => $row['Answer'],
                            'selected_options' => []
                        ];
                    }
                    if ($row['QuestionOptionID']) {
                        $grouped_answers[$qid]['selected_options'][] = [
                            'option_id' => $row['QuestionOptionID'],
                            'option_title' => $row['OptionTitle'],
                            'additional_text' => $row['QuestionOptionText']
                        ];
                    }
                }

                $response_data[] = [
                    'submission_id' => $sid,
                    'submit_ip' => $sub['SubmitIP'],
                    'submit_agent' => $sub['SubmitAgent'],
                    'create_date' => $sub['CreateDate'],
                    'answers' => array_values($grouped_answers)
                ];
            }
        }

        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'form' => $form,
                'total_submissions' => count($response_data),
                'submissions' => $response_data
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}

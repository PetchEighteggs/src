<?php
require_once '../../config.php';
require_once '../../helper.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

global $DB;

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 1;

try {
    if (isset($DB)) {
        // ดึงข้อมูลฟอร์ม
        $sqlForm = "SELECT * FROM survey_form WHERE FormID = :form_id AND IsActive = 'Y'";
        $stmtForm = $DB->prepare($sqlForm);
        $stmtForm->bindValue(':form_id', $form_id, PDO::PARAM_INT);
        $stmtForm->execute();
        $form = $stmtForm->fetch(PDO::FETCH_ASSOC);

        if (!$form) {
            echo json_encode(['status' => 'error', 'code' => 404, 'message' => 'Survey form not found or inactive']);
            exit;
        }

        // ดึง Sections
        $sqlSections = "SELECT * FROM survey_section WHERE FormID = :form_id ORDER BY SectionOrder ASC";
        $stmtSec = $DB->prepare($sqlSections);
        $stmtSec->execute([':form_id' => $form_id]);
        $sectionsRaw = $stmtSec->fetchAll(PDO::FETCH_ASSOC);

        // ดึงคำถามทั้งหมด
        $sqlQuestions = "
            SELECT q.*, qt.QuestionType 
            FROM survey_question q
            LEFT JOIN survey_question_type qt ON q.QuestionID = qt.QuestionID
            WHERE q.FormID = :form_id AND q.Publish = 'Y' AND q.DelData = 'N'
            ORDER BY q.QuestionOrder ASC
        ";
        $stmtQ = $DB->prepare($sqlQuestions);
        $stmtQ->execute([':form_id' => $form_id]);
        $questionsRaw = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        // ดึงตัวเลือกทั้งหมด (เฉพาะฟอร์มนี้)
        $sqlOptions = "
            SELECT o.* 
            FROM survey_question_option o
            JOIN survey_question q ON o.QuestionID = q.QuestionID
            WHERE q.FormID = :form_id AND o.Publish = 'Y' 
            ORDER BY o.QuestionID ASC, o.QuestionOptionOrder ASC
        ";
        $stmtOpt = $DB->prepare($sqlOptions);
        $stmtOpt->execute([':form_id' => $form_id]);
        $optionsRaw = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);

        // จัดกลุ่ม Options ตาม QuestionID
        $optionsByQ = [];
        foreach ($optionsRaw as $opt) {
            $qid = $opt['QuestionID'];
            if (!isset($optionsByQ[$qid])) $optionsByQ[$qid] = [];
            $optionsByQ[$qid][] = [
                'option_id' => (int)$opt['QuestionOptionID'],
                'group_name' => $opt['GroupName'],
                'title' => $opt['Title'],
                'status_other' => $opt['StatusOther']
            ];
        }

        // แยกคำถามหลักและคำถามย่อย (Matrix rows)
        $parentQuestions = [];
        $subQuestions = [];

        foreach ($questionsRaw as $q) {
            $qFormatted = [
                'question_id' => (int)$q['QuestionID'],
                'title' => $q['Title'],
                'description' => $q['Description'],
                'required' => $q['Required'] === 'Y',
                'type' => $q['QuestionType'] ?? 'text',
                'options' => $optionsByQ[$q['QuestionID']] ?? []
            ];

            if ($q['ParentQuestionID'] > 0) {
                $subQuestions[$q['ParentQuestionID']][] = $qFormatted;
            } else {
                $qFormatted['section_id'] = $q['SectionID'];
                $parentQuestions[] = $qFormatted;
            }
        }

        // นำ Sub Questions ไปผูกกับ Parent Matrix
        foreach ($parentQuestions as &$pq) {
            if ($pq['type'] === 'matrix') {
                $pq['sub_questions'] = $subQuestions[$pq['question_id']] ?? [];
            }
        }
        unset($pq);

        // จัดกลุ่ม Parent Questions ลง Section
        $sections = [];
        foreach ($sectionsRaw as $sec) {
            $sections[$sec['SectionID']] = [
                'section_id' => (int)$sec['SectionID'],
                'title' => $sec['Title'],
                'description' => $sec['Description'],
                'questions' => []
            ];
        }

        $uncategorized = [];
        foreach ($parentQuestions as $pq) {
            $secId = $pq['section_id'];
            unset($pq['section_id']); // ไม่ต้องโชว์ใน output
            if ($secId && isset($sections[$secId])) {
                $sections[$secId]['questions'][] = $pq;
            } else {
                $uncategorized[] = $pq;
            }
        }

        $finalSections = array_values($sections);
        if (!empty($uncategorized)) {
            $finalSections[] = [
                'section_id' => null,
                'title' => 'Other',
                'description' => null,
                'questions' => $uncategorized
            ];
        }

        $response = [
            'form_id' => (int)$form['FormID'],
            'form_name' => $form['FormName'],
            'description' => $form['Description'],
            'sections' => $finalSections
        ];

        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'data' => $response
        ]);
    } else {
        throw new Exception("Database connection not found.");
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

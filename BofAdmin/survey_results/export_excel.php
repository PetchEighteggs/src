<?php

declare(strict_types=1);

define('_VALID_ACCESS', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../configuration.php');
require_once('../../config/ms_com.php');
require_once('../includes/SpreadsheetExporter.php');

if (!isset($_SESSION['BofAdmin_LoggedIn']) || $_SESSION['BofAdmin_LoggedIn'] !== true) {
    header('Location: ../login.php');
    exit;
}

$DB = mosConnectPDO();
if (!$DB instanceof PDO) {
    http_response_code(503);
    exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
}

$formId = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$formId) {
    http_response_code(400);
    exit('รหัสแบบสอบถามไม่ถูกต้อง');
}

// Enforce the same section-level access used by Backoffice pages.
if (($_SESSION['GroupID'] ?? '') === 'S') {
    $sectionStmt = $DB->prepare("
        SELECT SectionID
        FROM bof_section
        WHERE SectionURL = 'survey_results/index.php' OR SectionURL LIKE 'survey_results/%'
        LIMIT 1
    ");
    $sectionStmt->execute();
    $sectionId = $sectionStmt->fetchColumn();

    // Match the current Backoffice rule: enforce RBAC only when the section is registered.
    if ($sectionId !== false) {
        $permissionStmt = $DB->prepare("
            SELECT CanAdd, CanEdit, CanDelete, CanApprove
            FROM staff_permission
            WHERE SetGroupID = :group_id
              AND SectionID = :section_id
              AND (DelData IS NULL OR DelData = 'N')
            LIMIT 1
        ");
        $permissionStmt->execute([
            ':group_id' => (int) ($_SESSION['SetGroupID'] ?? 0),
            ':section_id' => (int) $sectionId,
        ]);

        $permission = $permissionStmt->fetch(PDO::FETCH_ASSOC);
        $canView = $permission && in_array('Y', [
            $permission['CanAdd'] ?? 'N',
            $permission['CanEdit'] ?? 'N',
            $permission['CanDelete'] ?? 'N',
            $permission['CanApprove'] ?? 'N',
        ], true);

        if (!$canView) {
            http_response_code(403);
            exit('คุณไม่มีสิทธิ์ส่งออกข้อมูลนี้');
        }
    }
}

try {
    $formStmt = $DB->prepare('SELECT FormID, FormName FROM survey_form WHERE FormID = :form_id');
    $formStmt->execute([':form_id' => $formId]);
    $form = $formStmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) {
        http_response_code(404);
        exit('ไม่พบแบบสอบถาม');
    }

    $questionStmt = $DB->prepare("
        SELECT
            q.QuestionID,
            q.Title,
            q.Description,
            q.ParentQuestionID,
            parent.Title AS ParentTitle,
            qt.QuestionType
        FROM survey_question q
        LEFT JOIN survey_question parent ON parent.QuestionID = q.ParentQuestionID
        LEFT JOIN survey_question_type qt ON qt.QuestionID = q.QuestionID
        LEFT JOIN survey_section section_data ON section_data.SectionID = q.SectionID
        WHERE q.FormID = :form_id
          AND q.Publish = 'Y'
          AND q.DelData = 'N'
          AND NOT (COALESCE(qt.QuestionType, '') = 'matrix' AND q.ParentQuestionID = 0)
        ORDER BY COALESCE(section_data.SectionOrder, 999999), q.QuestionOrder, q.QuestionID
    ");
    $questionStmt->execute([':form_id' => $formId]);

    $questions = [];
    while ($question = $questionStmt->fetch(PDO::FETCH_ASSOC)) {
        $title = trim((string) $question['Title']);
        if (!empty($question['Description'])) {
            $title .= ' (' . trim((string) $question['Description']) . ')';
        }
        if (!empty($question['ParentTitle'])) {
            $title = trim((string) $question['ParentTitle']) . ' → ' . $title;
        }

        $questions[(int) $question['QuestionID']] = $title;
    }

    $responseStmt = $DB->prepare("
        SELECT
            submission.SubmissionID,
            submission.SubmitIP,
            submission.CreateDate,
            answer.QuestionID,
            answer.Answer,
            answer_option.QuestionOptionID,
            question_option.Title AS OptionTitle,
            answer_option.QuestionOptionText
        FROM survey_submission submission
        LEFT JOIN survey_answer answer
            ON answer.SubmissionID = submission.SubmissionID
        LEFT JOIN survey_answer_option answer_option
            ON answer_option.AnswerID = answer.AnswerID
        LEFT JOIN survey_question_option question_option
            ON question_option.QuestionOptionID = answer_option.QuestionOptionID
        WHERE submission.FormID = :form_id
        ORDER BY submission.CreateDate, submission.SubmissionID, answer.AnswerID, answer_option.AnswerOptionID
    ");
    $responseStmt->execute([':form_id' => $formId]);

    $submissions = [];
    while ($record = $responseStmt->fetch(PDO::FETCH_ASSOC)) {
        $submissionId = (int) $record['SubmissionID'];
        if (!isset($submissions[$submissionId])) {
            $submissions[$submissionId] = [
                'submit_ip' => (string) ($record['SubmitIP'] ?? ''),
                'create_date' => (string) ($record['CreateDate'] ?? ''),
                'answers' => [],
            ];
        }

        $questionId = (int) ($record['QuestionID'] ?? 0);
        if ($questionId <= 0 || !isset($questions[$questionId])) {
            continue;
        }

        $answerText = '';
        if (!empty($record['QuestionOptionID'])) {
            $answerText = trim((string) ($record['OptionTitle'] ?? ''));
            if (!empty($record['QuestionOptionText'])) {
                $answerText .= ' (' . trim((string) $record['QuestionOptionText']) . ')';
            }
        } elseif ($record['Answer'] !== null && $record['Answer'] !== '') {
            $answerText = (string) $record['Answer'];
        }

        if ($answerText !== '') {
            $submissions[$submissionId]['answers'][$questionId][] = $answerText;
        }
    }

    $headers = array_merge(
        ['ลำดับ', 'วันที่-เวลาที่ตอบ', 'IP Address'],
        array_values($questions)
    );

    $rows = (function () use ($submissions, $questions): Generator {
        $sequence = 1;
        foreach ($submissions as $submission) {
            $timestamp = strtotime($submission['create_date']);
            $row = [
                (string) $sequence++,
                $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : $submission['create_date'],
                $submission['submit_ip'],
            ];

            foreach ($questions as $questionId => $_title) {
                $row[] = isset($submission['answers'][$questionId])
                    ? implode(', ', $submission['answers'][$questionId])
                    : '-';
            }

            yield $row;
        }
    })();

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    SpreadsheetExporter::downloadXlsx(
        'SurveyResults_Form' . $formId . '_' . date('Ymd_His') . '.xlsx',
        'ผลแบบสอบถาม ' . $formId,
        $headers,
        $rows,
        [
            'ชื่อแบบสอบถาม' => $form['FormName'],
            'จำนวนการตอบทั้งหมด' => count($submissions),
            'วันที่ส่งออกข้อมูล' => date('d/m/Y H:i:s'),
        ],
        [1 => 10, 2 => 22, 3 => 18]
    );
} catch (Throwable $exception) {
    error_log('Survey Excel export failed: ' . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    exit('ไม่สามารถส่งออกข้อมูลได้');
}

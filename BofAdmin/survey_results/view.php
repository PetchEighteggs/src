<?php

define('_VALID_ACCESS', 1);
$pageTitle = 'ผลการตอบแบบสอบถาม (Survey Responses)';
include_once('../includes/header.php');

function surveyValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

$formId = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$formId) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$dateFrom = surveyValidDate($dateFrom) ? $dateFrom : '';
$dateTo = surveyValidDate($dateTo) ? $dateTo : '';
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$form = null;
$questions = [];
$optionCharts = [];
$trendLabels = [];
$trendSeries = [];
$summary = ['total' => 0, 'today' => 0, 'last_response' => null];
$loadError = '';

try {
    if (!isset($DB) || !$DB instanceof PDO) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $formStmt = $DB->prepare('SELECT FormID, FormName, Description FROM survey_form WHERE FormID = :form_id');
    $formStmt->execute([':form_id' => $formId]);
    $form = $formStmt->fetch(PDO::FETCH_ASSOC);
    if (!$form) {
        throw new RuntimeException('ไม่พบแบบสอบถามนี้');
    }

    $questionStmt = $DB->prepare("
        SELECT q.QuestionID, q.Title, q.Description, q.ParentQuestionID,
               parent.Title AS ParentTitle, qt.QuestionType
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
    while ($question = $questionStmt->fetch(PDO::FETCH_ASSOC)) {
        $title = trim((string) $question['Title']);
        if (!empty($question['ParentTitle'])) {
            $title = trim((string) $question['ParentTitle']) . ' → ' . $title;
        }
        $questions[(int) $question['QuestionID']] = [
            'title' => $title,
            'description' => trim((string) ($question['Description'] ?? '')),
            'type' => (string) ($question['QuestionType'] ?? 'text'),
        ];
    }

    $dateSql = '';
    $dateParams = [':form_id' => $formId];
    if ($dateFrom !== '') {
        $dateSql .= ' AND submission.CreateDate >= :date_from';
        $dateParams[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $dateSql .= ' AND submission.CreateDate < :date_to';
        $dateParams[':date_to'] = date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00';
    }

    $summaryStmt = $DB->prepare("
        SELECT COUNT(*) AS total,
               SUM(DATE(submission.CreateDate) = CURDATE()) AS today,
               MAX(submission.CreateDate) AS last_response
        FROM survey_submission submission
        WHERE submission.FormID = :form_id {$dateSql}
    ");
    $summaryStmt->execute($dateParams);
    $summaryData = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary = [
        'total' => (int) ($summaryData['total'] ?? 0),
        'today' => (int) ($summaryData['today'] ?? 0),
        'last_response' => $summaryData['last_response'] ?? null,
    ];

    $trendStmt = $DB->prepare("
        SELECT DATE(submission.CreateDate) AS response_date, COUNT(*) AS response_count
        FROM survey_submission submission
        WHERE submission.FormID = :form_id {$dateSql}
        GROUP BY DATE(submission.CreateDate)
        ORDER BY response_date
    ");
    $trendStmt->execute($dateParams);
    while ($trend = $trendStmt->fetch(PDO::FETCH_ASSOC)) {
        $trendLabels[] = date('d/m/Y', strtotime($trend['response_date']));
        $trendSeries[] = (int) $trend['response_count'];
    }

    $chartStmt = $DB->prepare("
        SELECT answer.QuestionID, option_data.QuestionOptionID,
               option_data.Title AS option_title, COUNT(*) AS answer_count
        FROM survey_submission submission
        INNER JOIN survey_answer answer ON answer.SubmissionID = submission.SubmissionID
        INNER JOIN survey_answer_option answer_option ON answer_option.AnswerID = answer.AnswerID
        INNER JOIN survey_question_option option_data ON option_data.QuestionOptionID = answer_option.QuestionOptionID
        WHERE submission.FormID = :form_id {$dateSql}
        GROUP BY answer.QuestionID, option_data.QuestionOptionID, option_data.Title
        ORDER BY answer.QuestionID, answer_count DESC, option_data.QuestionOptionOrder
    ");
    $chartStmt->execute($dateParams);
    while ($chartRow = $chartStmt->fetch(PDO::FETCH_ASSOC)) {
        $questionId = (int) $chartRow['QuestionID'];
        if (!isset($questions[$questionId]) || !in_array($questions[$questionId]['type'], ['radio', 'select', 'checkbox'], true)) {
            continue;
        }
        $optionCharts[$questionId]['labels'][] = (string) $chartRow['option_title'];
        $optionCharts[$questionId]['series'][] = (int) $chartRow['answer_count'];
    }

    foreach ($optionCharts as &$chart) {
        if (count($chart['labels']) <= 10) {
            continue;
        }
        $remainingCount = array_sum(array_slice($chart['series'], 10));
        $chart['labels'] = array_slice($chart['labels'], 0, 10);
        $chart['series'] = array_slice($chart['series'], 0, 10);
        $chart['labels'][] = 'ตัวเลือกอื่น ๆ';
        $chart['series'][] = $remainingCount;
    }
    unset($chart);
} catch (Throwable $exception) {
    error_log('Survey results dashboard failed: ' . $exception->getMessage());
    $loadError = $exception->getMessage() === 'ไม่พบแบบสอบถามนี้'
        ? $exception->getMessage()
        : 'ไม่สามารถโหลดข้อมูลผลแบบสอบถามได้';
}

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<?php if ($loadError !== ''): ?>
<div class="glass-panel p-8 text-center">
    <i class="ph ph-warning-circle text-5xl text-red-400"></i>
    <h2 class="mt-3 text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($loadError); ?></h2>
    <a href="index.php" class="btn-primary inline-flex mt-5">กลับหน้ารายการ</a>
</div>
<?php else: ?>

<div class="glass-panel p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div class="flex items-center gap-4 min-w-0">
        <a href="index.php" aria-label="ย้อนกลับ" title="ย้อนกลับ" class="w-10 h-10 shrink-0 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div class="min-w-0">
            <h2 class="text-xl font-semibold text-gray-800 break-words">ผลลัพธ์: <?php echo htmlspecialchars($form['FormName']); ?></h2>
            <?php if (!empty($form['Description'])): ?>
                <p class="text-sm text-gray-500 mt-1 max-w-3xl"><?php echo htmlspecialchars($form['Description']); ?></p>
            <?php endif; ?>
        </div>
        </div>
        <div class="flex flex-wrap items-center gap-3 pl-14 md:pl-0">
            <a href="export_excel.php?<?php echo http_build_query(['form_id' => $formId]); ?>" class="btn-primary h-10 px-6 flex items-center justify-center gap-2 rounded-lg font-medium transition-colors bg-green-600 hover:bg-green-700">
                <i class="ph ph-file-xls text-lg"></i> Export Excel
            </a>
        </div>
    </div>

    <form method="get" class="bg-gray-50 p-4 rounded-xl border border-gray-200 mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-[minmax(200px,1fr)_minmax(200px,1fr)_auto] gap-4 items-end">
        <input type="hidden" name="form_id" value="<?php echo $formId; ?>">
        <div>
            <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-2">ตั้งแต่วันที่</label>
            <div class="relative">
                <input id="dateFrom" type="text" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full h-10 pl-4 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400"><i class="ph ph-calendar text-lg"></i></div>
            </div>
        </div>
        <div>
            <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-2">ถึงวันที่</label>
            <div class="relative">
                <input id="dateTo" type="text" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full h-10 pl-4 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-white cursor-pointer" placeholder="วว/ดด/ปปปป" readonly>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400"><i class="ph ph-calendar text-lg"></i></div>
            </div>
        </div>
        <div class="sm:col-span-2 xl:col-span-1 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary h-10 px-6 rounded-lg flex items-center justify-center gap-2">
                <i class="ph ph-funnel"></i> แสดงผล
            </button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                <a href="view.php?form_id=<?php echo $formId; ?>" class="h-10 px-5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center justify-center gap-2">
                    <i class="ph ph-x-circle"></i> ล้างค่า
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
    <div class="glass-panel p-5 flex items-center justify-between">
        <div><p class="text-sm text-gray-500">จำนวนการตอบ<?php echo ($dateFrom || $dateTo) ? 'ในช่วงที่เลือก' : 'ทั้งหมด'; ?></p><p class="text-3xl font-bold text-gray-800 mt-1"><?php echo number_format($summary['total']); ?></p></div>
        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center"><i class="ph ph-users-three text-2xl"></i></div>
    </div>
    <div class="glass-panel p-5 flex items-center justify-between">
        <div><p class="text-sm text-gray-500">คำตอบวันนี้</p><p class="text-3xl font-bold text-gray-800 mt-1"><?php echo number_format($summary['today']); ?></p></div>
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="ph ph-calendar-check text-2xl"></i></div>
    </div>
    <div class="glass-panel p-5 flex items-center justify-between">
        <div><p class="text-sm text-gray-500">คำตอบล่าสุด</p><p class="text-lg font-bold text-gray-800 mt-1"><?php echo $summary['last_response'] ? date('d/m/Y H:i', strtotime($summary['last_response'])) : '-'; ?></p></div>
        <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center"><i class="ph ph-clock-countdown text-2xl"></i></div>
    </div>
</div>

<div class="glass-panel p-6 mb-6">
    <h3 class="font-bold text-lg text-gray-800">แนวโน้มการตอบแบบสอบถาม</h3>
    <p class="text-sm text-gray-500 mt-1">จำนวนการส่งคำตอบแยกตามวัน</p>
    <div id="surveyTrendChart" class="h-72 mt-4"></div>
</div>

<?php if ($optionCharts !== []): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <?php foreach ($optionCharts as $questionId => $chart): ?>
    <div class="glass-panel p-6">
        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($questions[$questionId]['title']); ?></h3>
        <?php if ($questions[$questionId]['description'] !== ''): ?><p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($questions[$questionId]['description']); ?></p><?php endif; ?>
        <div id="questionChart<?php echo $questionId; ?>" class="mt-4" style="min-height: 260px;"></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr !== 'undefined') {
        let dateToPicker;
        const dateFromPicker = flatpickr('#dateFrom', {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: 'th',
            allowInput: true,
            onChange: function (selectedDates) {
                if (dateToPicker) {
                    dateToPicker.set('minDate', selectedDates[0] || null);
                }
            }
        });
        dateToPicker = flatpickr('#dateTo', {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: 'th',
            allowInput: true,
            minDate: dateFromPicker.selectedDates[0] || null,
            onChange: function (selectedDates) {
                dateFromPicker.set('maxDate', selectedDates[0] || null);
            }
        });
    }

    const common = { chart: { fontFamily: 'Kanit, sans-serif', toolbar: { show: false } }, dataLabels: { enabled: false }, noData: { text: 'ยังไม่มีข้อมูล' } };
    new ApexCharts(document.querySelector('#surveyTrendChart'), {
        ...common,
        series: [{ name: 'จำนวนการตอบ', data: <?php echo json_encode($trendSeries, $jsonFlags); ?> }],
        chart: { ...common.chart, type: 'area', height: 285 },
        colors: ['#005A9C'],
        stroke: { curve: 'smooth', width: 3 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?php echo json_encode($trendLabels, $jsonFlags); ?> },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.floor(value) } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 }
    }).render();

    <?php foreach ($optionCharts as $questionId => $chart): ?>
    new ApexCharts(document.querySelector('#questionChart<?php echo $questionId; ?>'), {
        ...common,
        series: [{ name: 'จำนวนคำตอบ', data: <?php echo json_encode($chart['series'], $jsonFlags); ?> }],
        chart: { ...common.chart, type: 'bar', height: Math.max(260, <?php echo count($chart['labels']); ?> * 42) },
        colors: ['#009FE3'],
        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '60%' } },
        xaxis: { categories: <?php echo json_encode($chart['labels'], $jsonFlags); ?>, min: 0, labels: { formatter: value => Math.floor(value) } },
        tooltip: { y: { formatter: value => value.toLocaleString('th-TH') + ' คำตอบ' } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 }
    }).render();
    <?php endforeach; ?>
});
</script>

<?php endif; ?>
<?php include_once('../includes/footer.php'); ?>

<?php
// /api/export_applications.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/auth.php';
require_login();
$uid = current_user_id();

require_once __DIR__ . '/../api/db.php';

/* -------------------------------------------------------
 * Load PhpSpreadsheet (Composer or manual autoload)
 * ----------------------------------------------------- */
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',         // public_html/vendor/autoload.php
    dirname(__DIR__, 2) . '/vendor/autoload.php',// one level above public_html
    __DIR__ . '/autoload.php',                   // manual fallback under /api
];

$autoloadOk = false;
foreach ($autoloadCandidates as $cand) {
    if (is_file($cand)) {
        require_once $cand;
        $autoloadOk = true;
        break;
    }
}

if (!$autoloadOk) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PhpSpreadsheet autoloader not found.\n\n".
         "You have two options:\n".
         "  1) Composer (recommended):\n".
         "     cd /home/interviewly.xyz/public_html\n".
         "     composer require phpoffice/phpspreadsheet\n\n".
         "  2) Manual fallback:\n".
         "     - Put the PhpSpreadsheet sources under /vendor/PhpOffice/PhpSpreadsheet/\n".
         "     - Create an autoloader at /vendor/autoload.php or /api/autoload.php\n".
         "       that maps the PhpOffice\\PhpSpreadsheet\\ namespace to that folder.\n\n".
         "Tried paths:\n - " . implode("\n - ", $autoloadCandidates) . "\n";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// 1) Fetch data
$sql = "SELECT company, position, job_type, status, location, job_link, source,
               applied_date, next_action_date, salary_range, notes
        FROM applications
        WHERE user_id = :uid
        ORDER BY applied_date DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $uid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2) Build spreadsheet
$sheetTitle = 'Applications';
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Tracklly')
    ->setTitle('Tracklly Applications')
    ->setSubject('Export')
    ->setDescription('Exported applications from Tracklly.');

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle($sheetTitle);

// Columns & headers
$columns = [
    'A' => 'Company',
    'B' => 'Position',
    'C' => 'Job Type',
    'D' => 'Status',
    'E' => 'Location',
    'F' => 'Job Link',
    'G' => 'Source',
    'H' => 'Applied Date',
    'I' => 'Next Action Date',
    'J' => 'Salary Range',
    'K' => 'Notes',
];

// Header
foreach ($columns as $col => $label) {
    $sheet->setCellValue("{$col}1", $label);
}
$headerRange = "A1:K1";
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937'); // dark header
$sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(22);

// Freeze header
$sheet->freezePane('A2');

// 3) Status color map
$statusColors = [
    'Pending'    => 'FFFACC15', // yellow-ish
    'Applied'    => 'FFEAB308', // amber
    'Interview'  => 'FF38BDF8', // sky
    'Accepted'   => 'FF22C55E', // green
    'Rejected'   => 'FFEF4444', // red
    'Offer'      => 'FF10B981', // teal
    'On Hold'    => 'FF94A3B8', // slate
];

// 4) Body rows
$r = 2;
foreach ($rows as $row) {
    $sheet->setCellValue("A{$r}", $row['company']);
    $sheet->setCellValue("B{$r}", $row['position']);
    $sheet->setCellValue("C{$r}", $row['job_type']);
    $sheet->setCellValue("D{$r}", $row['status']);
    $sheet->setCellValue("E{$r}", $row['location']);

    // Hyperlink for Job Link (F)
    if (!empty($row['job_link'])) {
        $sheet->setCellValue("F{$r}", $row['job_link']);
        $sheet->getCell("F{$r}")->getHyperlink()->setUrl($row['job_link']);
        $sheet->getStyle("F{$r}")->getFont()->getColor()->setARGB('FF2563EB'); // blue link
        $sheet->getStyle("F{$r}")->getFont()->setUnderline(true);
    }

    $sheet->setCellValue("G{$r}", $row['source']);
    $sheet->setCellValue("H{$r}", $row['applied_date']);
    $sheet->setCellValue("I{$r}", $row['next_action_date']);
    $sheet->setCellValue("J{$r}", $row['salary_range']);

    // Notes can be long; wrap text
    $sheet->setCellValue("K{$r}", $row['notes']);
    $sheet->getStyle("K{$r}")->getAlignment()->setWrapText(true);

    // Color the Status cell background
    $status = trim((string)$row['status']);
    $color = $statusColors[$status] ?? 'FFCBD5E1'; // default: light slate
    $sheet->getStyle("D{$r}")
        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
    $sheet->getStyle("D{$r}")->getFont()->getColor()->setARGB(Color::COLOR_WHITE);

    $r++;
}

// 5) Borders + column sizing
$dataRange = "A1:K" . ($r - 1);
$sheet->getStyle($dataRange)->getBorders()->getAllBorders()
      ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF374151');
foreach (array_keys($columns) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getColumnDimension('K')->setWidth(60); // wider Notes

// 6) Output
$filename = 'Tracklly_' . date('d_m_Y-H_i') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;